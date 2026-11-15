<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class HomeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{id: int, title: string, imageUrl: string, targetUrl: string, sortOrder: int}>
     */
    public function heroBanners(int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, title, image_url, target_url, sort_order
             FROM hero_banners
             WHERE status = 'active'
               AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP(6))
               AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP(6))
             ORDER BY sort_order ASC, id ASC
             LIMIT :limit",
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'imageUrl' => (string) $row['image_url'],
                'targetUrl' => (string) $row['target_url'],
                'sortOrder' => (int) $row['sort_order'],
            ],
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bestSellers(int $limit): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
WITH recent_sales AS (
    SELECT offer.product_id, SUM(item.quantity) AS units_sold
    FROM orders AS purchase
    JOIN order_items AS item ON item.order_id = purchase.id
    JOIN offers AS offer ON offer.id = item.offer_id
    WHERE purchase.status = 'paid'
      AND purchase.paid_at >= UTC_TIMESTAMP(6) - INTERVAL 30 DAY
    GROUP BY offer.product_id
),
inventory AS (
    SELECT offer_id, COUNT(*) AS available_quantity
    FROM digital_keys
    WHERE status = 'available'
    GROUP BY offer_id
),
ranked_offers AS (
    SELECT
        offer.id AS offer_id,
        offer.product_id,
        offer.price_minor,
        offer.compare_at_price_minor,
        offer.currency,
        platform.code AS platform_code,
        platform.name AS platform_name,
        region.code AS region_code,
        region.name AS region_name,
        COALESCE(inventory.available_quantity, 0) AS available_quantity,
        ROW_NUMBER() OVER (
            PARTITION BY offer.product_id
            ORDER BY offer.price_minor ASC, offer.id ASC
        ) AS offer_rank
    FROM offers AS offer
    JOIN seller_profiles AS seller
      ON seller.id = offer.seller_id AND seller.status = 'approved'
    JOIN platforms AS platform
      ON platform.id = offer.platform_id AND platform.status = 'active'
    JOIN regions AS region
      ON region.id = offer.region_id AND region.status = 'active'
    LEFT JOIN inventory ON inventory.offer_id = offer.id
    WHERE offer.status = 'active'
      AND offer.currency = 'EUR'
),
selected AS (
    SELECT *
    FROM ranked_offers
    WHERE offer_rank = 1
)
SELECT
    product.id,
    product.slug,
    product.name,
    product.description,
    product.image_url,
    selected.offer_id,
    selected.price_minor,
    selected.compare_at_price_minor,
    selected.currency,
    selected.platform_code,
    selected.platform_name,
    selected.region_code,
    selected.region_name,
    selected.available_quantity,
    recent_sales.units_sold
FROM recent_sales
JOIN products AS product ON product.id = recent_sales.product_id
LEFT JOIN selected ON selected.product_id = product.id
WHERE product.status = 'active'
ORDER BY recent_sales.units_sold DESC, product.name ASC, product.id ASC
LIMIT :limit
SQL);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function specialDeals(int $limit): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
WITH inventory AS (
    SELECT offer_id, COUNT(*) AS available_quantity
    FROM digital_keys
    WHERE status = 'available'
    GROUP BY offer_id
)
SELECT
    offer.id AS offer_id,
    product.id AS product_id,
    product.slug AS product_slug,
    product.name AS product_name,
    product.image_url,
    offer.price_minor,
    offer.compare_at_price_minor,
    offer.currency,
    platform.code AS platform_code,
    platform.name AS platform_name,
    region.code AS region_code,
    region.name AS region_name,
    COALESCE(inventory.available_quantity, 0) AS available_quantity,
    FLOOR(
        (offer.compare_at_price_minor - offer.price_minor)
        * 100.0 / offer.compare_at_price_minor
    ) AS discount_percent
FROM offers AS offer
JOIN products AS product
  ON product.id = offer.product_id AND product.status = 'active'
JOIN seller_profiles AS seller
  ON seller.id = offer.seller_id AND seller.status = 'approved'
JOIN platforms AS platform
  ON platform.id = offer.platform_id AND platform.status = 'active'
JOIN regions AS region
  ON region.id = offer.region_id AND region.status = 'active'
LEFT JOIN inventory ON inventory.offer_id = offer.id
WHERE offer.status = 'active'
  AND offer.compare_at_price_minor > offer.price_minor
  AND (offer.deal_starts_at IS NULL OR offer.deal_starts_at <= UTC_TIMESTAMP(6))
  AND (offer.deal_ends_at IS NULL OR offer.deal_ends_at > UTC_TIMESTAMP(6))
GROUP BY
    offer.id,
    product.id,
    product.slug,
    product.name,
    product.image_url,
    offer.price_minor,
    offer.compare_at_price_minor,
    offer.currency,
    platform.code,
    platform.name,
    region.code,
    region.name,
    inventory.available_quantity
ORDER BY discount_percent DESC, offer.price_minor ASC, offer.id ASC
LIMIT :limit
SQL);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @return list<array{id: int, slug: string, name: string, unitsSold: int}>
     */
    public function popularCategories(int $limit): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    category.id,
    category.slug,
    category.name,
    SUM(item.quantity) AS units_sold
FROM orders AS purchase
JOIN order_items AS item ON item.order_id = purchase.id
JOIN offers AS offer ON offer.id = item.offer_id
JOIN products AS product ON product.id = offer.product_id AND product.status = 'active'
JOIN product_categories AS product_category ON product_category.product_id = product.id
JOIN categories AS category
  ON category.id = product_category.category_id AND category.status = 'active'
WHERE purchase.status = 'paid'
  AND purchase.paid_at >= UTC_TIMESTAMP(6) - INTERVAL 30 DAY
GROUP BY category.id, category.slug, category.name
ORDER BY units_sold DESC, category.sort_order ASC, category.name ASC, category.id ASC
LIMIT :limit
SQL);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'unitsSold' => (int) $row['units_sold'],
            ],
            $statement->fetchAll(),
        );
    }
}
