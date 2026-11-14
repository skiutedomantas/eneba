<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $user = $pdo->prepare(
        "INSERT INTO users (email, password_hash, display_name, role, status)
         VALUES (:email, :password_hash, 'Catalog Demo Seller', 'customer', 'disabled')
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
    );
    $user->execute([
        'email' => 'catalog-seller@example.invalid',
        'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    if ($userId === 0) {
        $lookupUser = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $lookupUser->execute(['email' => 'catalog-seller@example.invalid']);
        $userId = (int) $lookupUser->fetchColumn();
    }

    $seller = $pdo->prepare(
        "INSERT INTO seller_profiles (user_id, display_name, status)
         VALUES (:user_id, 'Catalog Demo Seller', 'approved')
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
    );
    $seller->execute(['user_id' => $userId]);
    $sellerId = (int) $pdo->lastInsertId();

    if ($sellerId === 0) {
        $lookupSeller = $pdo->prepare('SELECT id FROM seller_profiles WHERE user_id = :user_id');
        $lookupSeller->execute(['user_id' => $userId]);
        $sellerId = (int) $lookupSeller->fetchColumn();
    }

    $offer = $pdo->prepare(<<<'SQL'
INSERT INTO offers (
    product_id,
    seller_id,
    platform_id,
    region_id,
    price_minor,
    compare_at_price_minor,
    currency,
    status
)
SELECT
    product.id,
    :seller_id,
    platform.id,
    region.id,
    :price_minor,
    :compare_at_price_minor,
    'EUR',
    'active'
FROM products AS product
JOIN platforms AS platform ON platform.code = :platform_code
JOIN regions AS region ON region.code = :region_code
WHERE product.slug = :product_slug
  AND NOT EXISTS (
      SELECT 1
      FROM offers AS existing
      WHERE existing.product_id = product.id
        AND existing.seller_id = :existing_seller_id
        AND existing.platform_id = platform.id
        AND existing.region_id = region.id
        AND existing.currency = 'EUR'
  )
SQL);

    foreach ([
        [
            'product_slug' => 'starlight-odyssey',
            'platform_code' => 'pc',
            'region_code' => 'global',
            'price_minor' => 1899,
            'compare_at_price_minor' => 4999,
        ],
        [
            'product_slug' => 'iron-harbor',
            'platform_code' => 'playstation-5',
            'region_code' => 'eu',
            'price_minor' => 2499,
            'compare_at_price_minor' => 4499,
        ],
        [
            'product_slug' => 'wildwood-trail',
            'platform_code' => 'pc',
            'region_code' => 'us',
            'price_minor' => 1299,
            'compare_at_price_minor' => null,
        ],
    ] as $values) {
        $offer->execute([
            'seller_id' => $sellerId,
            'price_minor' => $values['price_minor'],
            'compare_at_price_minor' => $values['compare_at_price_minor'],
            'platform_code' => $values['platform_code'],
            'region_code' => $values['region_code'],
            'product_slug' => $values['product_slug'],
            'existing_seller_id' => $sellerId,
        ]);
    }
};
