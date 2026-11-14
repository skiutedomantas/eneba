<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LookupRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function categories(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, slug, name
             FROM categories
             WHERE status = 'active'
             ORDER BY sort_order ASC, name ASC, id ASC
             LIMIT 100",
        )->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
            ],
            $rows,
        );
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function platforms(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, code, name
             FROM platforms
             WHERE status = 'active'
             ORDER BY name ASC, id ASC
             LIMIT 100",
        )->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ],
            $rows,
        );
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function regions(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, code, name
             FROM regions
             WHERE status = 'active'
             ORDER BY name ASC, id ASC
             LIMIT 100",
        )->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ],
            $rows,
        );
    }
}
