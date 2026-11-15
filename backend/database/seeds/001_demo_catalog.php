<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $categories = [
        ['action', 'Action', 10],
        ['role-playing', 'Role-playing', 20],
        ['adventure', 'Adventure', 30],
        ['strategy', 'Strategy', 40],
        ['simulation', 'Simulation', 50],
        ['racing', 'Racing', 60],
        ['horror', 'Horror', 70],
        ['platformer', 'Platformer', 80],
        ['indie', 'Indie', 90],
        ['shooter', 'Shooter', 100],
        ['survival', 'Survival', 110],
    ];
    $insertCategory = $pdo->prepare(
        'INSERT INTO categories (slug, name, status, sort_order)
         VALUES (:slug, :name, \'active\', :sort_order)
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            status = \'active\',
            sort_order = VALUES(sort_order)',
    );
    foreach ($categories as [$slug, $name, $sortOrder]) {
        $insertCategory->execute([
            'slug' => $slug,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    $platforms = [
        ['pc', 'PC'],
        ['playstation-5', 'PlayStation 5'],
        ['xbox-series', 'Xbox Series X|S'],
    ];
    $insertPlatform = $pdo->prepare(
        'INSERT INTO platforms (code, name, status)
         VALUES (:code, :name, \'active\')
         ON DUPLICATE KEY UPDATE name = VALUES(name), status = \'active\'',
    );
    foreach ($platforms as [$code, $name]) {
        $insertPlatform->execute(['code' => $code, 'name' => $name]);
    }

    $regions = [
        ['global', 'Global'],
        ['eu', 'Europe'],
        ['us', 'United States'],
    ];
    $insertRegion = $pdo->prepare(
        'INSERT INTO regions (code, name, status)
         VALUES (:code, :name, \'active\')
         ON DUPLICATE KEY UPDATE name = VALUES(name), status = \'active\'',
    );
    foreach ($regions as [$code, $name]) {
        $insertRegion->execute(['code' => $code, 'name' => $name]);
    }

    $products = [
        [
            'minecraft',
            'Minecraft',
            'Build, explore, and survive in a procedurally generated sandbox.',
            ['adventure', 'simulation'],
        ],
        [
            'grand-theft-auto-v',
            'Grand Theft Auto V',
            'Explore a large open world through three connected criminal stories.',
            ['action', 'adventure'],
        ],
        [
            'red-dead-redemption-2',
            'Red Dead Redemption 2',
            'Follow an outlaw gang across a detailed frontier landscape.',
            ['action', 'adventure'],
        ],
        [
            'the-witcher-3-wild-hunt',
            'The Witcher 3: Wild Hunt',
            'Track monsters and make consequential choices in a fantasy role-playing world.',
            ['action', 'role-playing'],
        ],
        [
            'cyberpunk-2077',
            'Cyberpunk 2077',
            'Explore Night City in a story-driven open-world role-playing game.',
            ['action', 'role-playing'],
        ],
        [
            'elden-ring',
            'Elden Ring',
            'Explore a vast fantasy world and face challenging enemies and bosses.',
            ['action', 'role-playing'],
        ],
        [
            'baldurs-gate-3',
            'Baldur\'s Gate 3',
            'Make party-based choices and explore a character-driven fantasy adventure.',
            ['role-playing', 'adventure'],
        ],
        [
            'hogwarts-legacy',
            'Hogwarts Legacy',
            'Attend a magical school and explore its surrounding open world.',
            ['action', 'adventure'],
        ],
        [
            'helldivers-2',
            'Helldivers 2',
            'Team up in a cooperative science-fiction third-person shooter.',
            ['action', 'shooter'],
        ],
        [
            'palworld',
            'Palworld',
            'Collect creatures, craft equipment, and build in an open-world survival game.',
            ['action', 'survival'],
        ],
        [
            'stardew-valley',
            'Stardew Valley',
            'Restore a farm and build relationships in a relaxed life simulation.',
            ['simulation', 'role-playing', 'indie'],
        ],
        [
            'terraria',
            'Terraria',
            'Dig, craft, and explore a two-dimensional sandbox adventure.',
            ['action', 'adventure', 'indie'],
        ],
        [
            'rust',
            'Rust',
            'Gather resources and survive in a persistent multiplayer world.',
            ['action', 'survival'],
        ],
        [
            'dead-by-daylight',
            'Dead by Daylight',
            'Play an asymmetric multiplayer horror game as a survivor or hunter.',
            ['horror', 'action'],
        ],
        [
            'resident-evil-4',
            'Resident Evil 4',
            'Survive a rescue mission in a third-person action horror game.',
            ['action', 'horror'],
        ],
        [
            'monster-hunter-wilds',
            'Monster Hunter Wilds',
            'Track formidable creatures and prepare for hunts in a changing environment.',
            ['action', 'role-playing'],
        ],
        [
            'forza-horizon-5',
            'Forza Horizon 5',
            'Race across a large open-world driving festival.',
            ['racing', 'simulation'],
        ],
        [
            'sea-of-thieves',
            'Sea of Thieves',
            'Sail, explore, and take on voyages in a shared pirate adventure.',
            ['action', 'adventure'],
        ],
        [
            'marvels-spider-man-remastered',
            'Marvel\'s Spider-Man Remastered',
            'Swing through a city in a story-driven superhero action adventure.',
            ['action', 'adventure'],
        ],
        [
            'god-of-war',
            'God of War',
            'Travel through a mythological realm in a cinematic action adventure.',
            ['action', 'adventure'],
        ],
        [
            'ghost-of-tsushima-directors-cut',
            'Ghost of Tsushima Director\'s Cut',
            'Explore feudal Japan in an open-world samurai action adventure.',
            ['action', 'adventure'],
        ],
        [
            'final-fantasy-vii-rebirth',
            'Final Fantasy VII Rebirth',
            'Continue a party-based role-playing journey through a broad fantasy world.',
            ['role-playing', 'action'],
        ],
        [
            'hades',
            'Hades',
            'Fight through an ever-changing underworld in a fast-paced action game.',
            ['action', 'indie'],
        ],
        [
            'hollow-knight-silksong',
            'Hollow Knight: Silksong',
            'Explore a hand-crafted world in a challenging action-platform adventure.',
            ['action', 'platformer'],
        ],
        [
            'black-myth-wukong',
            'Black Myth: Wukong',
            'Journey through a myth-inspired world in an action role-playing game.',
            ['action', 'role-playing'],
        ],
        [
            'assassins-creed-shadows',
            'Assassin\'s Creed Shadows',
            'Explore a historical open world through two distinct playable characters.',
            ['action', 'adventure'],
        ],
        [
            'sid-meiers-civilization-vi',
            'Sid Meier\'s Civilization VI',
            'Build and guide a civilization through a turn-based strategy campaign.',
            ['strategy', 'simulation'],
        ],
        [
            'dragons-dogma-2',
            'Dragon\'s Dogma 2',
            'Embark on a fantasy adventure with a customizable party of companions.',
            ['action', 'role-playing'],
        ],
        [
            'the-elder-scrolls-v-skyrim-special-edition',
            'The Elder Scrolls V: Skyrim Special Edition',
            'Explore a northern fantasy province in an open-world role-playing game.',
            ['role-playing', 'adventure'],
        ],
        [
            'death-stranding-directors-cut',
            'Death Stranding Director\'s Cut',
            'Travel across a fragmented landscape in a story-driven delivery adventure.',
            ['adventure', 'simulation'],
        ],
    ];

    $insertProduct = $pdo->prepare(
        'INSERT IGNORE INTO products (slug, name, description, status)
         VALUES (:slug, :name, :description, \'active\')',
    );
    $refreshProduct = $pdo->prepare(
        'UPDATE products
         SET name = :name, description = :description, status = \'active\'
         WHERE slug = :slug',
    );
    $categoryId = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug');
    $insertProductCategory = $pdo->prepare(
        'INSERT IGNORE INTO product_categories (product_id, category_id)
         SELECT product.id, :category_id
         FROM products AS product
         WHERE product.slug = :product_slug',
    );
    foreach ($products as [$slug, $name, $description, $productCategories]) {
        $values = ['slug' => $slug, 'name' => $name, 'description' => $description];
        $insertProduct->execute($values);
        $refreshProduct->execute($values);

        foreach ($productCategories as $productCategory) {
            $categoryId->execute(['slug' => $productCategory]);
            $id = $categoryId->fetchColumn();
            $categoryId->closeCursor();
            if ($id === false) {
                throw new \RuntimeException('A seeded product category is missing.');
            }
            $insertProductCategory->execute([
                'category_id' => (int) $id,
                'product_slug' => $slug,
            ]);
        }
    }

    $pdo->exec(<<<'SQL'
INSERT INTO hero_banners (title, image_url, target_url, sort_order, status)
SELECT 'Find your next adventure', '/images/hero-banner.svg', '/store', 1, 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM hero_banners WHERE title = 'Find your next adventure'
)
SQL);
};
