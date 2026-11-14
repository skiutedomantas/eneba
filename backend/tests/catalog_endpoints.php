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

$all = $catalog->listProducts([]);
expect($all['meta']['total'] === 3, 'Expected all three active demo products.');
expect(count($all['data']) === 3, 'Expected all demo products on the first page.');
expect(
    $all['data'][0]['lowestOffer']['availableQuantity'] === 0,
    'Catalog fixture offers must not imply unseeded key inventory.',
);

$firstPage = $catalog->listProducts(['page' => '1', 'perPage' => '1']);
$secondPage = $catalog->listProducts(['page' => '2', 'perPage' => '1']);
expect($firstPage['meta']['total'] === 3, 'Pagination must preserve the total count.');
expect(count($firstPage['data']) === 1, 'Expected one product on a one-item page.');
expect(
    $firstPage['data'][0]['id'] !== $secondPage['data'][0]['id'],
    'Adjacent pages must contain different products.',
);

$platformFiltered = $catalog->listProducts(['platform' => 'pc']);
expect($platformFiltered['meta']['total'] === 2, 'Platform filtering returned an unexpected count.');

$categoryFiltered = $catalog->listProducts(['category' => 'action']);
expect($categoryFiltered['meta']['total'] === 1, 'Category filtering returned an unexpected count.');

$priceFiltered = $catalog->listProducts([
    'minPriceMinor' => '2000',
    'maxPriceMinor' => '3000',
]);
expect($priceFiltered['meta']['total'] === 1, 'Minor-unit price filtering returned an unexpected count.');
expect(
    $priceFiltered['data'][0]['slug'] === 'iron-harbor',
    'Price filtering did not return the expected product.',
);

$priceSorted = $catalog->listProducts(['sort' => 'price_asc']);
expect(
    $priceSorted['data'][0]['slug'] === 'wildwood-trail',
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

expect(count($lookups->categories([])['data']) === 3, 'Expected the active category lookup data.');
expect(count($lookups->platforms([])['data']) === 3, 'Expected the active platform lookup data.');
expect(count($lookups->regions([])['data']) === 3, 'Expected the active region lookup data.');
expect(count($home->heroBanners([])['data']) === 1, 'Expected the seeded active hero banner.');
expect($home->bestSellers([])['data'] === [], 'Best sellers must remain empty without completed sales.');
expect($home->specialDeals([])['data'] === [], 'Special deals must remain empty without available keys.');
expect(
    $home->popularCategories([])['data'] === [],
    'Popular categories must remain empty without completed sales.',
);

fwrite(STDOUT, "Catalog and homepage service checks passed.\n");
