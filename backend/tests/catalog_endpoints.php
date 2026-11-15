<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\HomeRepository;
use App\Repositories\LookupRepository;
use App\Repositories\ProductRepository;
use App\Services\CatalogService;
use App\Services\HomeService;
use App\Services\LookupService;
use App\Support\ValidationError;

require dirname(__DIR__) . '/bootstrap.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Connection::create();
$catalog = new CatalogService(new ProductRepository($pdo));
$home = new HomeService(new HomeRepository($pdo));
$lookups = new LookupService(new LookupRepository($pdo));

$offerSummary = $pdo->query(
    "SELECT
        COUNT(*) AS active_offers,
        COUNT(DISTINCT platform_id) AS platforms,
        COUNT(DISTINCT region_id) AS regions,
        SUM(compare_at_price_minor > price_minor) AS discounted_offers,
        SUM(currency != 'EUR') AS non_eur_offers
     FROM offers
     WHERE status = 'active'",
)->fetch();
expect(
    (int) $offerSummary['active_offers'] === 61
        && (int) $offerSummary['platforms'] === 3
        && (int) $offerSummary['regions'] === 3
        && (int) $offerSummary['discounted_offers'] >= 45
        && (int) $offerSummary['non_eur_offers'] === 0,
    'Seeded offers should have varied pricing, discounts, platforms, and regions.',
);
$offerCoverage = $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT product.id
    FROM products AS product
    LEFT JOIN offers AS offer
      ON offer.product_id = product.id AND offer.status = 'active'
    WHERE product.status = 'active'
    GROUP BY product.id
    HAVING COUNT(offer.id) < 2
        OR COUNT(offer.id) > 3
        OR COUNT(DISTINCT offer.seller_id) < 2
) AS invalid_coverage
SQL)->fetchColumn();
expect(
    (int) $offerCoverage === 0,
    'Every active game should have two or three offers from distinct sellers.',
);
expect(
    (int) $pdo->query('SELECT COUNT(*) FROM digital_keys')->fetchColumn() === 0,
    'The catalog seed must not add digital-key inventory.',
);

$all = $catalog->listProducts(['perPage' => '48']);
expect($all['meta']['total'] === 30, 'Expected all 30 active games.');
expect(count($all['data']) === 30, 'Expected all games on the first page.');
$productsWithOffers = array_values(array_filter(
    $all['data'],
    static fn (array $product): bool => $product['lowestOffer'] !== null,
));
expect(
    count($productsWithOffers) === 30
        && $productsWithOffers[0]['lowestOffer']['availableQuantity'] === 0,
    'Catalog results should retain key inventory counts from the backend.',
);
expect(
    !in_array(
        'starlight-odyssey',
        array_column($all['data'], 'slug'),
        true,
    ),
    'Legacy catalog products must be removed from the active catalog.',
);

$firstPage = $catalog->listProducts(['page' => '1', 'perPage' => '1']);
$secondPage = $catalog->listProducts(['page' => '2', 'perPage' => '1']);
expect($firstPage['meta']['total'] === 30, 'Pagination must preserve the total count.');
expect(count($firstPage['data']) === 1, 'Expected one product on a one-item page.');
expect(
    $firstPage['data'][0]['id'] !== $secondPage['data'][0]['id'],
    'Adjacent pages must contain different products.',
);

$platformFiltered = $catalog->listProducts(['platform' => 'pc']);
expect($platformFiltered['meta']['total'] === 30, 'Every game should have a PC offer.');
$xboxFiltered = $catalog->listProducts(['platform' => 'xbox-series']);
expect(
    $xboxFiltered['meta']['total'] > 1
        && in_array('cyberpunk-2077', array_column($xboxFiltered['data'], 'slug'), true),
    'Xbox platform filtering should find offers across the catalog.',
);
$usFiltered = $catalog->listProducts(['region' => 'us']);
expect(
    $usFiltered['meta']['total'] > 1
        && in_array('stardew-valley', array_column($usFiltered['data'], 'slug'), true),
    'United States region filtering should return matching offers.',
);

$categoryFiltered = $catalog->listProducts(['category' => 'action', 'perPage' => '48']);
expect(
    $categoryFiltered['meta']['total'] > 1
        && in_array('cyberpunk-2077', array_column($categoryFiltered['data'], 'slug'), true),
    'Category filtering should include action games and the reassigned seller offer.',
);

$priceFiltered = $catalog->listProducts([
    'minPriceMinor' => '2000',
    'maxPriceMinor' => '3000',
]);
expect($priceFiltered['meta']['total'] > 0, 'Minor-unit price filtering should find offers.');
foreach ($priceFiltered['data'] as $filteredProduct) {
    expect(
        $filteredProduct['lowestOffer']['priceMinor'] >= 2000
            && $filteredProduct['lowestOffer']['priceMinor'] <= 3000,
        'Price filtering returned a product outside the requested range.',
    );
}

$priceSorted = $catalog->listProducts(['sort' => 'price_asc']);
expect(
    $priceSorted['data'][0]['slug'] === 'terraria',
    'Price ascending sorting did not select the least expensive offer first.',
);

try {
    $catalog->listProducts(['sort' => 'untrusted']);
    throw new RuntimeException('Invalid sort was accepted.');
} catch (ValidationError $exception) {
    expect(isset($exception->fields['sort']), 'Invalid sort did not identify its field.');
}

try {
    $catalog->listProducts(['unexpected' => 'value']);
    throw new RuntimeException('An undocumented query parameter was accepted.');
} catch (ValidationError $exception) {
    expect(isset($exception->fields['unexpected']), 'Unknown query parameter was not identified.');
}

try {
    $home->specialDeals(['limit' => '21']);
    throw new RuntimeException('An out-of-range homepage limit was accepted.');
} catch (ValidationError $exception) {
    expect(isset($exception->fields['limit']), 'Invalid homepage limit did not identify its field.');
}

$categoryLookups = $lookups->categories([])['data'];
expect(count($categoryLookups) === 11, 'Expected the active category lookup data.');
expect(
    in_array('strategy', array_column($categoryLookups, 'slug'), true)
        && in_array('racing', array_column($categoryLookups, 'slug'), true)
        && in_array('horror', array_column($categoryLookups, 'slug'), true),
    'The catalog should expose varied active game categories.',
);
expect(count($lookups->platforms([])['data']) === 3, 'Expected the active platform lookup data.');
expect(count($lookups->regions([])['data']) === 3, 'Expected the active region lookup data.');
expect(count($home->heroBanners([])['data']) === 1, 'Expected the seeded active hero banner.');
$bestSellers = $home->bestSellers([])['data'];
expect(
    count($bestSellers) >= 1
        && $bestSellers[0]['unitsSold'] > 0
        && $bestSellers[0]['lowestOffer'] !== null,
    'Best sellers should be ranked from recent backend sales data.',
);
$specialDeals = $home->specialDeals([])['data'];
expect(
    count($specialDeals) >= 1
        && $specialDeals[0]['discountPercent'] > 0
        && $specialDeals[0]['availableQuantity'] === 0,
    'Discounted backend offers should display while reporting actual key inventory.',
);
$popularCategories = $home->popularCategories([])['data'];
expect(
    count($popularCategories) >= 1
        && $popularCategories[0]['unitsSold'] > 0,
    'Popular categories should be ranked from recent backend sales data.',
);

fwrite(STDOUT, "Catalog and homepage service checks passed.\n");
