<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\SqlDialect;
use PDO;

final class ProductSearchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{id: int, score: float}>
     */
    public function fullTextCandidates(
        string $normalizedQuery,
        string $prefixQuery,
        int $limit,
    ): array {
        if (SqlDialect::isPostgreSQL($this->pdo)) {
            $prefixTokens = array_filter(
                explode(' ', $normalizedQuery),
                static fn (string $token): bool => mb_strlen($token, 'UTF-8') >= 3,
            );
            $postgresPrefixQuery = implode(' & ', array_map(
                static fn (string $token): string => $token . ':*',
                $prefixTokens,
            ));
            $prefixScore = $postgresPrefixQuery === ''
                ? '0::real'
                : "ts_rank_cd(search_document, to_tsquery('simple', :prefix_score))";
            $prefixFilter = $postgresPrefixQuery === ''
                ? 'FALSE'
                : "search_document @@ to_tsquery('simple', :prefix_filter)";
            $statement = $this->pdo->prepare(<<<SQL
SELECT scored.id, scored.natural_score, scored.prefix_score
FROM (
SELECT
    product.id,
    ts_rank_cd(search_document, plainto_tsquery('simple', :natural_score)) AS natural_score,
    {$prefixScore} AS prefix_score
FROM (
    SELECT
        id,
        status,
        to_tsvector('simple', coalesce(name, '') || ' ' || coalesce(description, '')) AS search_document
    FROM products
) AS product
WHERE product.status = 'active'
  AND (
      product.search_document @@ plainto_tsquery('simple', :natural_filter)
      OR {$prefixFilter}
  )
) AS scored
ORDER BY (scored.natural_score + scored.prefix_score) DESC, scored.id ASC
LIMIT :limit
SQL);
            $statement->bindValue(':natural_score', $normalizedQuery);
            $statement->bindValue(':natural_filter', $normalizedQuery);
            if ($postgresPrefixQuery !== '') {
                $statement->bindValue(':prefix_score', $postgresPrefixQuery);
                $statement->bindValue(':prefix_filter', $postgresPrefixQuery);
            }
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();

            return array_map(
                static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'score' => (float) $row['natural_score'] + (float) $row['prefix_score'],
                ],
                $statement->fetchAll(),
            );
        }

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    product.id,
    MATCH(product.name, product.description)
        AGAINST (:natural_score IN NATURAL LANGUAGE MODE) AS natural_score,
    MATCH(product.name, product.description)
        AGAINST (:prefix_score IN BOOLEAN MODE) AS prefix_score
FROM products AS product
WHERE product.status = 'active'
  AND (
      MATCH(product.name, product.description)
          AGAINST (:natural_filter IN NATURAL LANGUAGE MODE) > 0
      OR MATCH(product.name, product.description)
          AGAINST (:prefix_filter IN BOOLEAN MODE) > 0
  )
ORDER BY (natural_score + prefix_score) DESC, product.id ASC
LIMIT :limit
SQL);
        $statement->bindValue(':natural_score', $normalizedQuery);
        $statement->bindValue(':prefix_score', $prefixQuery);
        $statement->bindValue(':natural_filter', $normalizedQuery);
        $statement->bindValue(':prefix_filter', $prefixQuery);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'score' => (float) $row['natural_score'] + (float) $row['prefix_score'],
            ],
            $statement->fetchAll(),
        );
    }

    /**
     * @param list<string> $grams
     * @return list<array{id: int, overlap: int}>
     */
    public function trigramCandidates(array $grams, int $limit): array
    {
        if ($grams === []) {
            return [];
        }

        $placeholders = [];
        $bindings = [];
        foreach ($grams as $index => $gram) {
            $placeholder = ':gram_' . $index;
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = $gram;
        }

        $statement = $this->pdo->prepare(
            'SELECT search_gram.product_id AS id, SUM(search_gram.weight) AS overlap
             FROM product_search_grams AS search_gram
             JOIN products AS product
               ON product.id = search_gram.product_id AND product.status = \'active\'
             WHERE search_gram.gram IN (' . implode(', ', $placeholders) . ')
             GROUP BY search_gram.product_id
             ORDER BY overlap DESC, search_gram.product_id ASC
             LIMIT :limit',
        );
        foreach ($bindings as $placeholder => $gram) {
            $statement->bindValue($placeholder, $gram);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'overlap' => (int) $row['overlap'],
            ],
            $statement->fetchAll(),
        );
    }
}
