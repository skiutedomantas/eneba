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
UPDATE products
SET image_url = CASE slug
    WHEN 'starlight-odyssey' THEN '/images/starlight-odyssey.svg'
    WHEN 'iron-harbor' THEN '/images/iron-harbor.svg'
    WHEN 'wildwood-trail' THEN '/images/wildwood-trail.svg'
    ELSE image_url
END
WHERE slug IN ('starlight-odyssey', 'iron-harbor', 'wildwood-trail')
  AND image_url IS NULL
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
    <<<'SQL'
INSERT INTO hero_banners (title, image_url, target_url, sort_order, status)
SELECT 'Find your next adventure', '/images/hero-banner.svg', '/store', 1, 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM hero_banners WHERE title = 'Find your next adventure'
)
SQL,
];
