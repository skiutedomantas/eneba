<?php

declare(strict_types=1);

return [
    <<<'SQL'
INSERT IGNORE INTO categories (slug, name, status, sort_order) VALUES
    ('action', 'Action', 'active', 10),
    ('role-playing', 'Role-playing', 'active', 20),
    ('adventure', 'Adventure', 'active', 30)
SQL,
    <<<'SQL'
INSERT IGNORE INTO platforms (code, name, status) VALUES
    ('pc', 'PC', 'active'),
    ('playstation-5', 'PlayStation 5', 'active'),
    ('xbox-series', 'Xbox Series X|S', 'active')
SQL,
    <<<'SQL'
INSERT IGNORE INTO regions (code, name, status) VALUES
    ('global', 'Global', 'active'),
    ('eu', 'Europe', 'active'),
    ('us', 'United States', 'active')
SQL,
    <<<'SQL'
INSERT IGNORE INTO products (slug, name, description, status) VALUES
    ('starlight-odyssey', 'Starlight Odyssey', 'Explore distant worlds in this fictional role-playing adventure.', 'active'),
    ('iron-harbor', 'Iron Harbor', 'Defend a coastal city in this fictional action strategy game.', 'active'),
    ('wildwood-trail', 'Wildwood Trail', 'Discover a changing forest in this fictional exploration game.', 'active')
SQL,
    <<<'SQL'
INSERT IGNORE INTO product_categories (product_id, category_id)
SELECT products.id, categories.id
FROM products
JOIN categories ON (
    (products.slug = 'starlight-odyssey' AND categories.slug = 'role-playing')
    OR (products.slug = 'iron-harbor' AND categories.slug = 'action')
    OR (products.slug = 'wildwood-trail' AND categories.slug = 'adventure')
)
SQL,
];
