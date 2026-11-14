<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ProductSearchText;
use PDO;

final class ProductSearchIndexService
{
    public static function rebuild(PDO $pdo): int
    {
        $products = $pdo->query(
            "SELECT id, name FROM products WHERE status = 'active' ORDER BY id ASC",
        )->fetchAll();

        $pdo->exec('DELETE FROM product_search_grams');
        $insert = $pdo->prepare(
            'INSERT INTO product_search_grams (product_id, gram, weight)
             VALUES (:product_id, :gram, 1)',
        );

        $indexedGrams = 0;
        foreach ($products as $product) {
            $normalizedName = ProductSearchText::normalize((string) $product['name']);
            foreach (ProductSearchText::grams($normalizedName) as $gram) {
                $insert->execute([
                    'product_id' => (int) $product['id'],
                    'gram' => $gram,
                ]);
                $indexedGrams++;
            }
        }

        return $indexedGrams;
    }
}
