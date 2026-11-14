<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class ProductRepository
{
    private const PRODUCT_CTES = <<<'SQL'
WITH inventory AS (
    SELECT offer_id, COUNT(*) AS available_quantity
    FROM digital_keys
    WHERE status = 'available'
    GROUP BY offer_id
),
paid_sales AS (
    SELECT offer.product_id, SUM(item.quantity) AS units_sold
    FROM orders AS purchase
    JOIN order_items AS item ON item.order_id = purchase.id
    JOIN offers AS offer ON offer.id = item.offer_id
    WHERE purchase.status = 'paid'
      AND purchase.paid_at >= UTC_TIMESTAMP(6) - INTERVAL 30 DAY
    GROUP BY offer.product_id
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
      AND offer.currency = :currency
SQL;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{
     *   page: int,
     *   perPage: int,
     *   platform: ?string,
     *   region: ?string,
     *   category: ?string,
     *   currency: string,
     *   minPriceMinor: ?int,
     *   maxPriceMinor: ?int,
     *   sort: string
     * } $filters
     * @return array{products: list<array<string, mixed>>, total: int}
     */
    public function listCatalog(array $filters): array
    {
        $bindings = [':currency' => $filters['currency']];
        $offerConditions = '';
        if ($filters['platform'] !== null) {
            $offerConditions .= ' AND platform.code = :platform';
            $bindings[':platform'] = $filters['platform'];
        }
        if ($filters['region'] !== null) {
            $offerConditions .= ' AND region.code = :region';
            $bindings[':region'] = $filters['region'];
        }

        $where = ["product.status = 'active'"];
        if ($filters['platform'] !== null || $filters['region'] !== null) {
            $where[] = 'selected.offer_id IS NOT NULL';
        }
        if ($filters['category'] !== null) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM product_categories AS product_category
                JOIN categories AS category ON category.id = product_category.category_id
                WHERE product_category.product_id = product.id
                  AND category.status = \'active\'
                  AND category.slug = :category
            )';
            $bindings[':category'] = $filters['category'];
        }
        if ($filters['minPriceMinor'] !== null) {
            $where[] = 'selected.price_minor >= :min_price_minor';
            $bindings[':min_price_minor'] = $filters['minPriceMinor'];
        }
        if ($filters['maxPriceMinor'] !== null) {
            $where[] = 'selected.price_minor <= :max_price_minor';
            $bindings[':max_price_minor'] = $filters['maxPriceMinor'];
        }
        if ($filters['minPriceMinor'] !== null || $filters['maxPriceMinor'] !== null) {
            $where[] = 'selected.offer_id IS NOT NULL';
        }

        $whereSql = implode("\n AND ", $where);
        $ctes = self::PRODUCT_CTES . $offerConditions . <<<'SQL'

),
selected AS (
    SELECT *
    FROM ranked_offers
    WHERE offer_rank = 1
)
SQL;

        $countStatement = $this->pdo->prepare(
            $ctes . "\nSELECT COUNT(*) FROM products AS product
             LEFT JOIN selected ON selected.product_id = product.id
             WHERE {$whereSql}",
        );
        $this->bindValues($countStatement, $bindings);
        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();

        $orderBy = match ($filters['sort']) {
            'price_asc' => 'CASE WHEN selected.price_minor IS NULL THEN 1 ELSE 0 END ASC, selected.price_minor ASC, product.id ASC',
            'price_desc' => 'CASE WHEN selected.price_minor IS NULL THEN 1 ELSE 0 END ASC, selected.price_minor DESC, product.id ASC',
            'popular' => 'COALESCE(sales.units_sold, 0) DESC, product.created_at DESC, product.id DESC',
            default => 'product.created_at DESC, product.id DESC',
        };

        $statement = $this->pdo->prepare(
            $ctes . <<<'SQL'

, category_sales AS (
    SELECT offer.product_id, SUM(item.quantity) AS units_sold
    FROM orders AS purchase
    JOIN order_items AS item ON item.order_id = purchase.id
    JOIN offers AS offer ON offer.id = item.offer_id
    WHERE purchase.status = 'paid'
      AND purchase.paid_at >= UTC_TIMESTAMP(6) - INTERVAL 30 DAY
    GROUP BY offer.product_id
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
    selected.available_quantity
FROM products AS product
LEFT JOIN selected ON selected.product_id = product.id
LEFT JOIN category_sales AS sales ON sales.product_id = product.id
WHERE
SQL
            . "\n"
            . $whereSql
            . "\nORDER BY {$orderBy}\nLIMIT :limit OFFSET :offset",
        );
        $this->bindValues($statement, $bindings);
        $statement->bindValue(':limit', $filters['perPage'], PDO::PARAM_INT);
        $statement->bindValue(
            ':offset',
            ($filters['page'] - 1) * $filters['perPage'],
            PDO::PARAM_INT,
        );
        $statement->execute();

        return ['products' => $statement->fetchAll(), 'total' => $total];
    }

    /**
     * @param array<string, string|int> $bindings
     */
    private function bindValues(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $name => $value) {
            $statement->bindValue(
                $name,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }
    }
}
