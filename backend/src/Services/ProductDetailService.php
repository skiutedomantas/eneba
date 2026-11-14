<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;

final class ProductDetailService
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @return array{data: array{product: array<string, mixed>, offers: list<array<string, mixed>>}}|null
     */
    public function getBySlug(string $slug): ?array
    {
        $row = $this->products->findActiveBySlug($slug);
        if ($row === null) {
            return null;
        }

        $offers = array_map(
            static function (array $offer): array {
                $price = (int) $offer['price_minor'];
                $compareAtPrice = $offer['compare_at_price_minor'] === null
                    ? null
                    : (int) $offer['compare_at_price_minor'];

                return [
                    'offerId' => (int) $offer['offer_id'],
                    'seller' => [
                        'id' => (int) $offer['seller_id'],
                        'name' => (string) $offer['seller_name'],
                    ],
                    'priceMinor' => $price,
                    'compareAtPriceMinor' => $compareAtPrice,
                    'currency' => (string) $offer['currency'],
                    'platform' => [
                        'code' => (string) $offer['platform_code'],
                        'name' => (string) $offer['platform_name'],
                    ],
                    'region' => [
                        'code' => (string) $offer['region_code'],
                        'name' => (string) $offer['region_name'],
                    ],
                    'availableQuantity' => (int) $offer['available_quantity'],
                    'discountPercent' => CatalogService::discountPercent($compareAtPrice, $price),
                ];
            },
            $this->products->activeOffers((int) $row['id']),
        );

        return [
            'data' => [
                'product' => [
                    'id' => (int) $row['id'],
                    'slug' => (string) $row['slug'],
                    'name' => (string) $row['name'],
                    'description' => (string) $row['description'],
                    'imageUrl' => $row['image_url'] === null ? null : (string) $row['image_url'],
                ],
                'offers' => $offers,
            ],
        ];
    }
}
