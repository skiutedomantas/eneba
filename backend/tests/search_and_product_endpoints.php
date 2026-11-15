<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\AuthRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSearchRepository;
use App\Services\ProductDetailService;
use App\Services\RateLimitService;
use App\Services\ProductSearchIndexService;
use App\Services\SearchService;
use App\Support\ApiException;
use App\Support\ValidationError;

require dirname(__DIR__) . '/bootstrap.php';

function expectSearch(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Connection::create();
$products = new ProductRepository($pdo);
$search = new SearchService($products, new ProductSearchRepository($pdo));
$details = new ProductDetailService($products);

$exact = $search->search(['q' => 'Cyberpunk 2077']);
expectSearch($exact['meta']['total'] === 1, 'Exact title search should find one product.');
expectSearch(
    $exact['data'][0]['slug'] === 'cyberpunk-2077',
    'Exact title search returned the wrong product.',
);

$normalized = $search->search(['q' => '  ＣＹＢＥＲＰＵＮＫ   ２０７７  ']);
expectSearch(
    $normalized['data'][0]['slug'] === 'cyberpunk-2077',
    'Unicode compatibility normalization and whitespace collapse failed.',
);
expectSearch(
    $normalized['meta']['q'] === 'cyberpunk 2077',
    'Search metadata should contain the normalized query.',
);

$partial = $search->search(['q' => 'cyberpunk 20']);
expectSearch(
    $partial['data'][0]['slug'] === 'cyberpunk-2077',
    'Partial word search did not return the matching product.',
);

$typo = $search->search(['q' => 'cyberpun 2077']);
expectSearch(
    $typo['data'][0]['slug'] === 'cyberpunk-2077',
    'Typo-tolerant search did not return the matching product.',
);

$midWordPartial = $search->search(['q' => 'berpunk 20']);
expectSearch(
    $midWordPartial['data'][0]['slug'] === 'cyberpunk-2077',
    'Mid-word partial search did not return the matching product.',
);

$noResults = $search->search(['q' => 'zzzznotfoundquery']);
expectSearch(
    $noResults['data'] === [] && $noResults['meta']['total'] === 0,
    'A non-matching query should return an empty result.',
);

$filtered = $search->search(['q' => 'cyberpunk', 'region' => 'eu']);
expectSearch(
    $filtered['meta']['total'] === 1
        && $filtered['data'][0]['lowestOffer']['region']['code'] === 'eu',
    'Search filters should apply to the matching seller offer.',
);

$paged = $search->search(['q' => 'cyberpunk', 'page' => '2', 'perPage' => '1']);
expectSearch(
    $paged['meta']['page'] === 2 && $paged['data'] === [],
    'Search pagination should use the filtered result count.',
);

$detail = $details->getBySlug('cyberpunk-2077');
expectSearch($detail !== null, 'The active product detail should be present.');
expectSearch(
    count($detail['data']['offers']) === 3,
    'Product detail should return each active approved seller offer.',
);
expectSearch(
    $detail['data']['offers'][0]['priceMinor'] <= $detail['data']['offers'][1]['priceMinor']
        && $detail['data']['offers'][1]['priceMinor'] <= $detail['data']['offers'][2]['priceMinor'],
    'Seller offers should be sorted by price.',
);
foreach ($detail['data']['offers'] as $offer) {
    $availableCount = $pdo->prepare(
        "SELECT COUNT(*) FROM digital_keys
         WHERE offer_id = :offer_id AND status = 'available'",
    );
    $availableCount->execute(['offer_id' => $offer['offerId']]);
    expectSearch(
        $offer['availableQuantity'] === (int) $availableCount->fetchColumn(),
        'Product detail inventory counts should match the database.',
    );
}
expectSearch(
    count(array_unique(array_column(array_column($detail['data']['offers'], 'seller'), 'name'))) === 3,
    'Offer comparison should identify each seller.',
);
expectSearch(
    count(array_unique(array_column(array_column($detail['data']['offers'], 'platform'), 'code'))) === 2
        && count(array_unique(array_column(array_column($detail['data']['offers'], 'region'), 'code'))) === 2,
    'Seller offers should cover multiple platforms and regions.',
);
expectSearch(
    count(array_column($detail['data']['offers'], 'availableQuantity')) === 3,
    'Product detail should include inventory counts for every active seller offer.',
);
expectSearch(
    $details->getBySlug('missing-product') === null,
    'Unknown product slugs should not return product details.',
);

try {
    $search->search(['q' => 'x']);
    throw new RuntimeException('A too-short search query was accepted.');
} catch (ValidationError $exception) {
    expectSearch(isset($exception->fields['q']), 'Short-query validation should identify q.');
}

try {
    $search->search(['q' => 'starlight', 'unknown' => 'value']);
    throw new RuntimeException('An unknown search parameter was accepted.');
} catch (ValidationError $exception) {
    expectSearch(
        isset($exception->fields['unknown']),
        'Unknown search parameters should be reported.',
    );
}

$pdo->beginTransaction();
try {
    $fixture = $pdo->prepare(
        'INSERT INTO products (slug, name, description, status)
         VALUES (:slug, :name, :description, \'active\')',
    );
    for ($index = 0; $index < 105; $index++) {
        $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        $fixture->execute([
            'slug' => 'phase3-search-fixture-' . $number,
            'name' => 'Fixture Candidate ' . $number,
            'description' => 'Temporary bounded-search integration fixture.',
        ]);
    }

    $inactive = $pdo->prepare(
        "INSERT INTO products (slug, name, description, status)
         VALUES ('phase3-inactive-product', 'Hidden Cyberpunk', 'Inactive test product', 'inactive')",
    );
    $inactive->execute();
    expectSearch(
        $details->getBySlug('phase3-inactive-product') === null,
        'Inactive products must not be available through product details.',
    );

    ProductSearchIndexService::rebuild($pdo);
    $bounded = (new SearchService(
        new ProductRepository($pdo),
        new ProductSearchRepository($pdo),
    ))->search(['q' => 'Fixture Candidate']);
    expectSearch(
        $bounded['meta']['total'] === 100
            && $bounded['meta']['candidateLimit'] === 100
            && $bounded['meta']['capped'] === true,
        'Search should cap and report its candidate set at 100 products.',
    );
} finally {
    $pdo->rollBack();
}

$pdo->beginTransaction();
try {
    $rateLimits = new RateLimitService(new AuthRepository($pdo));
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $rateLimits->assertAllowed('search', '203.0.113.79');
    }
    try {
        $rateLimits->assertAllowed('search', '203.0.113.79');
        throw new RuntimeException('The search rate limit was not enforced.');
    } catch (ApiException $exception) {
        expectSearch($exception->errorCode === 'RATE_LIMITED', 'Unexpected search rate-limit response.');
    }
} finally {
    $pdo->rollBack();
}

fwrite(STDOUT, "Search and product detail service checks passed.\n");
