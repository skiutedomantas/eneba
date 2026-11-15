<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $sellerIdFor = static function (string $email, string $displayName) use ($pdo): int {
        $user = $pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name, role, status)
             VALUES (:email, :password_hash, :display_name, \'customer\', \'disabled\')
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                display_name = VALUES(display_name)',
        );
        $user->execute([
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'display_name' => $displayName,
        ]);
        $userId = (int) $pdo->lastInsertId();
        if ($userId === 0) {
            $lookupUser = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $lookupUser->execute(['email' => $email]);
            $userId = (int) $lookupUser->fetchColumn();
        }

        $seller = $pdo->prepare(
            'INSERT INTO seller_profiles (user_id, display_name, status)
             VALUES (:user_id, :display_name, \'approved\')
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                display_name = VALUES(display_name),
                status = \'approved\'',
        );
        $seller->execute([
            'user_id' => $userId,
            'display_name' => $displayName,
        ]);
        $sellerId = (int) $pdo->lastInsertId();
        if ($sellerId === 0) {
            $lookupSeller = $pdo->prepare(
                'SELECT id FROM seller_profiles WHERE user_id = :user_id',
            );
            $lookupSeller->execute(['user_id' => $userId]);
            $sellerId = (int) $lookupSeller->fetchColumn();
        }

        return $sellerId;
    };

    $sellerIds = [];
    foreach ([
        ['catalog-seller@example.invalid', 'Pixel Vault'],
        ['compare-seller@example.invalid', 'Arcade Exchange'],
        ['regional-seller@example.invalid', 'Northstar Digital'],
        ['key-haven@example.invalid', 'Key Haven'],
        ['gamegrid@example.invalid', 'GameGrid'],
    ] as [$email, $displayName]) {
        $sellerIds[$email] = $sellerIdFor($email, $displayName);
    }

    $legacyOffers = $pdo->query(<<<'SQL'
SELECT offer.id, product.slug AS product_slug, account.email AS seller_email
FROM offers AS offer
JOIN products AS product ON product.id = offer.product_id
JOIN seller_profiles AS seller ON seller.id = offer.seller_id
JOIN users AS account ON account.id = seller.user_id
WHERE product.slug IN ('starlight-odyssey', 'iron-harbor', 'wildwood-trail')
ORDER BY offer.id
SQL)->fetchAll();
    $legacyOfferTarget = [
        'catalog-seller@example.invalid' => [
            'starlight-odyssey' => ['cyberpunk-2077', 'pc', 'global'],
            'iron-harbor' => ['baldurs-gate-3', 'playstation-5', 'eu'],
            'wildwood-trail' => ['stardew-valley', 'pc', 'us'],
        ],
        'compare-seller@example.invalid' => [
            'starlight-odyssey' => ['cyberpunk-2077', 'pc', 'global'],
        ],
        'regional-seller@example.invalid' => [
            'starlight-odyssey' => ['cyberpunk-2077', 'xbox-series', 'eu'],
        ],
    ];
    $legacyOfferUsage = $pdo->prepare(<<<'SQL'
SELECT
    EXISTS (SELECT 1 FROM digital_keys WHERE offer_id = :key_offer_id) AS has_keys,
    EXISTS (SELECT 1 FROM order_items WHERE offer_id = :order_offer_id) AS has_orders,
    EXISTS (SELECT 1 FROM cart_items WHERE offer_id = :cart_offer_id) AS has_cart_items
SQL);
    $moveLegacyOffer = $pdo->prepare(<<<'SQL'
UPDATE offers
SET product_id = (SELECT id FROM products WHERE slug = :product_slug),
    platform_id = (SELECT id FROM platforms WHERE code = :platform_code),
    region_id = (SELECT id FROM regions WHERE code = :region_code)
WHERE id = :offer_id
SQL);

    foreach ($legacyOffers as $legacyOffer) {
        $offerId = (int) $legacyOffer['id'];
        $legacyOfferUsage->execute([
            'key_offer_id' => $offerId,
            'order_offer_id' => $offerId,
            'cart_offer_id' => $offerId,
        ]);
        $usage = $legacyOfferUsage->fetch();
        if (
            (int) $usage['has_keys'] !== 0
            || (int) $usage['has_orders'] !== 0
            || (int) $usage['has_cart_items'] !== 0
        ) {
            throw new \RuntimeException(
                'Cannot reassign a fictional demo offer that has keys, orders, or cart items.',
            );
        }

        $target = $legacyOfferTarget[$legacyOffer['seller_email']][$legacyOffer['product_slug']]
            ?? null;
        if ($target === null) {
            throw new \RuntimeException('A fictional demo offer has no real-game reassignment.');
        }

        [$productSlug, $platformCode, $regionCode] = $target;
        $moveLegacyOffer->execute([
            'product_slug' => $productSlug,
            'platform_code' => $platformCode,
            'region_code' => $regionCode,
            'offer_id' => $offerId,
        ]);
    }

    $remainingLegacyOffers = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM offers AS offer
JOIN products AS product ON product.id = offer.product_id
WHERE product.slug IN ('starlight-odyssey', 'iron-harbor', 'wildwood-trail')
SQL)->fetchColumn();
    if ($remainingLegacyOffers !== 0) {
        throw new \RuntimeException('Fictional demo products still have seller offers.');
    }
    $pdo->exec(
        "DELETE FROM products
         WHERE slug IN ('starlight-odyssey', 'iron-harbor', 'wildwood-trail')",
    );

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
        AND existing.status = 'active'
  )
SQL);

    foreach ([
        [
            'product_slug' => 'cyberpunk-2077',
            'platform_code' => 'pc',
            'region_code' => 'global',
            'price_minor' => 1899,
            'compare_at_price_minor' => 4999,
        ],
        [
            'product_slug' => 'baldurs-gate-3',
            'platform_code' => 'playstation-5',
            'region_code' => 'eu',
            'price_minor' => 2499,
            'compare_at_price_minor' => 4499,
        ],
        [
            'product_slug' => 'stardew-valley',
            'platform_code' => 'pc',
            'region_code' => 'us',
            'price_minor' => 1299,
            'compare_at_price_minor' => null,
        ],
    ] as $values) {
        $offer->execute([
            'seller_id' => $sellerIds['catalog-seller@example.invalid'],
            'price_minor' => $values['price_minor'],
            'compare_at_price_minor' => $values['compare_at_price_minor'],
            'platform_code' => $values['platform_code'],
            'region_code' => $values['region_code'],
            'product_slug' => $values['product_slug'],
            'existing_seller_id' => $sellerIds['catalog-seller@example.invalid'],
        ]);
    }

    foreach ([
        [
            'seller_id' => $sellerIds['compare-seller@example.invalid'],
            'product_slug' => 'cyberpunk-2077',
            'platform_code' => 'pc',
            'region_code' => 'global',
            'price_minor' => 2199,
            'compare_at_price_minor' => 3999,
        ],
        [
            'seller_id' => $sellerIds['regional-seller@example.invalid'],
            'product_slug' => 'cyberpunk-2077',
            'platform_code' => 'xbox-series',
            'region_code' => 'eu',
            'price_minor' => 2499,
            'compare_at_price_minor' => null,
        ],
    ] as $values) {
        $offer->execute([
            'seller_id' => $values['seller_id'],
            'price_minor' => $values['price_minor'],
            'compare_at_price_minor' => $values['compare_at_price_minor'],
            'platform_code' => $values['platform_code'],
            'region_code' => $values['region_code'],
            'product_slug' => $values['product_slug'],
            'existing_seller_id' => $values['seller_id'],
        ]);
    }

    $sellerEmails = array_keys($sellerIds);
    $sellerIdsForProductQuery = $pdo->prepare(<<<'SQL'
SELECT seller_id
FROM offers
WHERE product_id = (SELECT id FROM products WHERE slug = :product_slug)
  AND status = 'active'
SQL);
    $activeOfferCountQuery = $pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM offers
WHERE product_id = (SELECT id FROM products WHERE slug = :product_slug)
  AND status = 'active'
SQL);
    $additionalOffers = [
        'minecraft' => [[2299, 3999], [2599, 3999]],
        'grand-theft-auto-v' => [[1999, 2999], [2299, 2999]],
        'red-dead-redemption-2' => [[2499, 5999], [2999, 5999]],
        'the-witcher-3-wild-hunt' => [[1499, 3999], [1999, 3999]],
        'elden-ring' => [[3999, 5999], [4499, 5999]],
        'baldurs-gate-3' => [[4499, 5999], [4999, 5999]],
        'hogwarts-legacy' => [[2999, 5999], [3499, 5999]],
        'helldivers-2' => [[2999, 3999], [3499, 3999]],
        'palworld' => [[2299, 2999], [2699, 2999]],
        'stardew-valley' => [[999, 1499], [1199, 1499]],
        'terraria' => [[749, 999], [849, 999]],
        'rust' => [[2799, 3999], [3299, 3999]],
        'dead-by-daylight' => [[1499, 2999], [1999, 2999]],
        'resident-evil-4' => [[2499, 3999], [2999, 3999]],
        'monster-hunter-wilds' => [[4999, 6999], [5499, 6999]],
        'forza-horizon-5' => [[2999, 5999], [3499, 5999]],
        'sea-of-thieves' => [[1999, 3999], [2499, 3999]],
        'marvels-spider-man-remastered' => [[2999, 5999], [3499, 5999]],
        'god-of-war' => [[1999, 3999], [2499, 3999]],
        'ghost-of-tsushima-directors-cut' => [[3499, 5999], [3999, 5999]],
        'final-fantasy-vii-rebirth' => [[4999, 6999], [5499, 6999]],
        'hades' => [[1599, 2499], [1999, 2499]],
        'hollow-knight-silksong' => [[1699, 1999], [1899, 1999]],
        'black-myth-wukong' => [[3999, 5999], [4499, 5999]],
        'assassins-creed-shadows' => [[4999, 6999], [5499, 6999]],
        'sid-meiers-civilization-vi' => [[599, 2999], [999, 2999]],
        'dragons-dogma-2' => [[2999, 5999], [3499, 5999]],
        'the-elder-scrolls-v-skyrim-special-edition' => [[999, 3999], [1499, 3999]],
        'death-stranding-directors-cut' => [[1999, 3999], [2499, 3999]],
    ];
    $platformSpecificQuotes = [
        'minecraft' => ['xbox-series', 'us', 2799, 3999],
        'grand-theft-auto-v' => ['xbox-series', 'us', 2199, 2999],
        'red-dead-redemption-2' => ['xbox-series', 'eu', 2799, 5999],
        'elden-ring' => ['playstation-5', 'eu', 4299, 5999],
        'hogwarts-legacy' => ['playstation-5', 'eu', 3199, 5999],
        'helldivers-2' => ['playstation-5', 'eu', 3199, 3999],
        'monster-hunter-wilds' => ['playstation-5', 'eu', 5299, 6999],
        'forza-horizon-5' => ['xbox-series', 'us', 3199, 5999],
        'ghost-of-tsushima-directors-cut' => ['playstation-5', 'eu', 3799, 5999],
        'assassins-creed-shadows' => ['xbox-series', 'eu', 5299, 6999],
    ];
    $quoteIndex = 0;
    foreach ($additionalOffers as $productSlug => $quotes) {
        $sellerIdsForProductQuery->execute(['product_slug' => $productSlug]);
        $usedSellerIds = array_map(
            'intval',
            $sellerIdsForProductQuery->fetchAll(\PDO::FETCH_COLUMN),
        );
        $activeOfferCountQuery->execute(['product_slug' => $productSlug]);
        $activeOfferCount = (int) $activeOfferCountQuery->fetchColumn();

        foreach ($quotes as $quoteNumber => [$priceMinor, $compareAtPriceMinor]) {
            if (
                $activeOfferCount >= 3
                || ($activeOfferCount >= 2 && count(array_unique($usedSellerIds)) >= 2)
            ) {
                break;
            }

            if ($quoteNumber === 1 && isset($platformSpecificQuotes[$productSlug])) {
                [
                    $platformCode,
                    $regionCode,
                    $priceMinor,
                    $compareAtPriceMinor,
                ] = $platformSpecificQuotes[$productSlug];
            } else {
                $platformCode = 'pc';
                $regionCode = 'global';
            }

            $availableSellerEmails = array_values(array_filter(
                $sellerEmails,
                static fn (string $email): bool => !in_array(
                    $sellerIds[$email],
                    $usedSellerIds,
                    true,
                ),
            ));
            if ($availableSellerEmails === []) {
                throw new \RuntimeException(
                    'No distinct seller is available for an additional game offer.',
                );
            }
            $sellerEmail = $availableSellerEmails[$quoteIndex % count($availableSellerEmails)];
            $sellerId = $sellerIds[$sellerEmail];
            $offer->execute([
                'seller_id' => $sellerId,
                'price_minor' => $priceMinor,
                'compare_at_price_minor' => $compareAtPriceMinor,
                'platform_code' => $platformCode,
                'region_code' => $regionCode,
                'product_slug' => $productSlug,
                'existing_seller_id' => $sellerId,
            ]);
            if ($offer->rowCount() > 0) {
                $usedSellerIds[] = $sellerId;
                $activeOfferCount++;
            }
            $quoteIndex++;
        }
    }

    $invalidOfferCoverage = (int) $pdo->query(<<<'SQL'
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
    if ($invalidOfferCoverage !== 0) {
        throw new \RuntimeException('Every active game must have two or three distinct-seller offers.');
    }
};
