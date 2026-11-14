<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\HomeRepository;
use App\Support\QueryParameters;
use App\Support\ValidationError;

final class HomeService
{
    public function __construct(private readonly HomeRepository $home)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    public function heroBanners(array $query): array
    {
        return $this->limited($query, 3, $this->home->heroBanners(...));
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    public function bestSellers(array $query): array
    {
        $limit = self::limit($query, 8);
        $rows = $this->home->bestSellers($limit);

        return [
            'data' => array_map(
                static function (array $row): array {
                    $product = CatalogService::presentProduct($row);
                    $product['unitsSold'] = (int) $row['units_sold'];
                    return $product;
                },
                $rows,
            ),
            'meta' => ['limit' => $limit],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    public function specialDeals(array $query): array
    {
        $limit = self::limit($query, 8);
        $rows = $this->home->specialDeals($limit);

        return [
            'data' => array_map(
                static fn (array $row): array => [
                    'offerId' => (int) $row['offer_id'],
                    'product' => [
                        'id' => (int) $row['product_id'],
                        'slug' => (string) $row['product_slug'],
                        'name' => (string) $row['product_name'],
                        'imageUrl' => $row['image_url'] === null
                            ? null
                            : (string) $row['image_url'],
                    ],
                    'priceMinor' => (int) $row['price_minor'],
                    'compareAtPriceMinor' => (int) $row['compare_at_price_minor'],
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
                    'discountPercent' => (int) $row['discount_percent'],
                ],
                $rows,
            ),
            'meta' => ['limit' => $limit],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    public function popularCategories(array $query): array
    {
        $limit = self::limit($query, 8);

        return [
            'data' => $this->home->popularCategories($limit),
            'meta' => ['limit' => $limit],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param callable(int): list<array<string, mixed>> $fetch
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    private function limited(array $query, int $default, callable $fetch): array
    {
        $limit = self::limit($query, $default);
        return ['data' => $fetch($limit), 'meta' => ['limit' => $limit]];
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function limit(array $query, int $default): int
    {
        if (array_diff(array_keys($query), ['limit']) !== []) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['query' => 'Only the limit parameter is supported.'],
            );
        }

        return QueryParameters::integer($query, 'limit', $default, 1, 20);
    }
}
