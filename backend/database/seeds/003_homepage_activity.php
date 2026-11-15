<?php

declare(strict_types=1);

use App\Database\SqlDialect;

return static function (\PDO $pdo): void {
    $accountSql = 'INSERT INTO users (email, password_hash, display_name, role, status)
         VALUES (:email, :password_hash, :display_name, \'customer\', \'disabled\')
         ';
    $accountSql .= SqlDialect::isPostgreSQL($pdo)
        ? 'ON CONFLICT (email) DO UPDATE SET display_name = users.display_name'
        : 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
    $userId = SqlDialect::insertId($pdo, $accountSql, [
        'email' => 'homepage-activity@example.invalid',
        'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        'display_name' => 'Marketplace Activity',
    ]);

    $sales = [
        ['cyberpunk-2077', 48],
        ['baldurs-gate-3', 42],
        ['minecraft', 36],
        ['hades', 32],
        ['forza-horizon-5', 28],
        ['dead-by-daylight', 24],
        ['hollow-knight-silksong', 20],
        ['helldivers-2', 18],
        ['rust', 16],
        ['sid-meiers-civilization-vi', 14],
    ];
    $findOffer = $pdo->prepare(<<<'SQL'
SELECT
    offer.id,
    offer.price_minor,
    offer.currency,
    product.name AS product_name,
    seller.display_name AS seller_name,
    platform.name AS platform_name,
    region.name AS region_name
FROM offers AS offer
JOIN products AS product ON product.id = offer.product_id
JOIN seller_profiles AS seller
  ON seller.id = offer.seller_id AND seller.status = 'approved'
JOIN platforms AS platform ON platform.id = offer.platform_id
JOIN regions AS region ON region.id = offer.region_id
WHERE product.slug = :product_slug
  AND product.status = 'active'
  AND offer.status = 'active'
  AND offer.currency = 'EUR'
ORDER BY offer.price_minor ASC, offer.id ASC
LIMIT 1
SQL);
    $createOrderSql = <<<'SQL'
INSERT INTO orders (
    user_id,
    status,
    currency,
    subtotal_minor,
    total_minor,
    idempotency_key,
    paid_at
)
VALUES (
    :user_id,
    'paid',
    :currency,
    :subtotal_minor,
    :total_minor,
    :idempotency_key,
    CURRENT_TIMESTAMP(6)
)
SQL;
    $createOrderSql .= SqlDialect::isPostgreSQL($pdo)
        ? ' ON CONFLICT (user_id, idempotency_key)
            DO UPDATE SET paid_at = CURRENT_TIMESTAMP(6)'
        : ' ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            paid_at = CURRENT_TIMESTAMP(6)';
    $insertItem = $pdo->prepare(<<<'SQL'
INSERT INTO order_items (
    order_id,
    offer_id,
    product_name_snapshot,
    seller_name_snapshot,
    platform_name_snapshot,
    region_name_snapshot,
    quantity,
    unit_price_minor,
    currency
)
SELECT
    :order_id,
    :offer_id,
    :product_name,
    :seller_name,
    :platform_name,
    :region_name,
    :quantity,
    :unit_price_minor,
    :currency
WHERE NOT EXISTS (
    SELECT 1 FROM order_items WHERE order_id = :existing_order_id
)
SQL);

    foreach ($sales as [$productSlug, $unitsSold]) {
        $findOffer->execute(['product_slug' => $productSlug]);
        $offer = $findOffer->fetch();
        if ($offer === false) {
            throw new \RuntimeException(
                'Homepage activity requires an active offer for every selected product.',
            );
        }

        $totalMinor = (int) $offer['price_minor'] * $unitsSold;
        $idempotencyKey = 'homepage-activity-' . $productSlug;
        $orderId = SqlDialect::insertId($pdo, $createOrderSql, [
            'user_id' => $userId,
            'currency' => $offer['currency'],
            'subtotal_minor' => $totalMinor,
            'total_minor' => $totalMinor,
            'idempotency_key' => $idempotencyKey,
        ]);

        $insertItem->execute([
            'order_id' => $orderId,
            'offer_id' => (int) $offer['id'],
            'product_name' => $offer['product_name'],
            'seller_name' => $offer['seller_name'],
            'platform_name' => $offer['platform_name'],
            'region_name' => $offer['region_name'],
            'quantity' => $unitsSold,
            'unit_price_minor' => (int) $offer['price_minor'],
            'currency' => $offer['currency'],
            'existing_order_id' => $orderId,
        ]);
    }
};
