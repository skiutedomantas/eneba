<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Support\QueryParameters;
use App\Support\ValidationError;

final class CatalogService
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|string>}
     */
    public function listProducts(array $query): array
    {
        $allowedParameters = [
            'page',
            'perPage',
            'platform',
            'region',
            'category',
            'currency',
            'minPriceMinor',
            'maxPriceMinor',
            'sort',
        ];
        $unknownParameters = array_diff(array_keys($query), $allowedParameters);
        if ($unknownParameters !== []) {
            $fields = [];
            foreach ($unknownParameters as $parameter) {
                $fields[(string) $parameter] = 'This query parameter is not supported.';
            }
            throw new ValidationError(
                'One or more query parameters are invalid.',
                $fields,
            );
        }

        $page = QueryParameters::integer($query, 'page', 1, 1, 10000);
        $perPage = QueryParameters::integer($query, 'perPage', 20, 1, 48);
        $platform = QueryParameters::optionalCode($query, 'platform');
        $region = QueryParameters::optionalCode($query, 'region');
        $category = QueryParameters::optionalCode($query, 'category', 100);
        $currency = QueryParameters::currency($query);
        $minPriceMinor = QueryParameters::optionalPrice($query, 'minPriceMinor');
        $maxPriceMinor = QueryParameters::optionalPrice($query, 'maxPriceMinor');

        if (
            $minPriceMinor !== null
            && $maxPriceMinor !== null
            && $minPriceMinor > $maxPriceMinor
        ) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['minPriceMinor' => 'Must not exceed maxPriceMinor.'],
            );
        }

        $sort = $query['sort'] ?? 'newest';
        if (
            !is_string($sort)
            || !in_array($sort, ['newest', 'price_asc', 'price_desc', 'popular'], true)
        ) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['sort' => 'Must be newest, price_asc, price_desc, or popular.'],
            );
        }

        $result = $this->products->listCatalog([
            'page' => $page,
            'perPage' => $perPage,
            'platform' => $platform,
            'region' => $region,
            'category' => $category,
            'currency' => $currency,
            'minPriceMinor' => $minPriceMinor,
            'maxPriceMinor' => $maxPriceMinor,
            'sort' => $sort,
        ]);

        return [
            'data' => array_map(
                static fn (array $row): array => self::presentProduct($row),
                $result['products'],
            ),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $result['total'],
                'sort' => $sort,
                'currency' => $currency,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function presentProduct(array $row): array
    {
        $offer = null;
        if ($row['offer_id'] !== null) {
            $price = (int) $row['price_minor'];
            $compareAtPrice = $row['compare_at_price_minor'] === null
                ? null
                : (int) $row['compare_at_price_minor'];

            $offer = [
                'offerId' => (int) $row['offer_id'],
                'priceMinor' => $price,
                'compareAtPriceMinor' => $compareAtPrice,
                'currency' => (string) $row['currency'],
                'platform' => [
                    'code' => (string) $row['platform_code'],
                    'name' => (string) $row['platform_name'],
                ],
                'region' => [
                    'code' => (string) $row['region_code'],
                    'name' => (string) $row['region_name'],
                ],
                'availableQuantity' => (int) $row['available_quantity'],
                'discountPercent' => self::discountPercent($compareAtPrice, $price),
            ];
        }

        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'imageUrl' => $row['image_url'] === null ? null : (string) $row['image_url'],
            'lowestOffer' => $offer,
        ];
    }

    public static function discountPercent(?int $compareAtPrice, int $price): ?int
    {
        if ($compareAtPrice === null || $compareAtPrice <= $price || $compareAtPrice === 0) {
            return null;
        }

        return (int) floor((($compareAtPrice - $price) / $compareAtPrice) * 100);
    }
}
