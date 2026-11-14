<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\ProductSearchRepository;
use App\Support\ProductSearchText;
use App\Support\ValidationError;
use InvalidArgumentException;

final class SearchService
{
    private const CANDIDATE_LIMIT = 100;
    private const CANDIDATE_POOL_LIMIT = 1000;
    private const FUZZY_FALLBACK_THRESHOLD = 8;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductSearchRepository $search,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|string|bool>}
     */
    public function search(array $query): array
    {
        $filters = CatalogService::parseFilters($query, true);
        $rawQuery = $query['q'] ?? null;
        if (!is_string($rawQuery)) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['q' => 'Must be valid UTF-8 search text.'],
            );
        }

        ProductSearchText::assertExtensionsAvailable();
        if (!mb_check_encoding($rawQuery, 'UTF-8')) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['q' => 'Must be valid UTF-8 search text.'],
            );
        }

        if (mb_strlen($rawQuery, 'UTF-8') > 200) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['q' => 'Must not exceed 200 characters.'],
            );
        }

        try {
            $normalizedQuery = ProductSearchText::normalize($rawQuery);
        } catch (InvalidArgumentException $exception) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['q' => 'Must be valid UTF-8 search text.'],
            );
        }

        $queryLength = mb_strlen($normalizedQuery, 'UTF-8');
        if ($queryLength < 2 || $queryLength > 100) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['q' => 'Must contain between 2 and 100 letters or numbers.'],
            );
        }

        $prefixTokens = array_filter(
            explode(' ', $normalizedQuery),
            static fn (string $token): bool => mb_strlen($token, 'UTF-8') >= 3,
        );
        $prefixQuery = implode(' ', array_map(
            static fn (string $token): string => $token . '*',
            $prefixTokens,
        ));

        $fullTextCandidates = $this->search->fullTextCandidates(
            $normalizedQuery,
            $prefixQuery,
            self::CANDIDATE_POOL_LIMIT,
        );
        $fullTextScores = [];
        $candidateSet = [];
        foreach ($fullTextCandidates as $candidate) {
            $fullTextScores[$candidate['id']] = $candidate['score'];
            $candidateSet[$candidate['id']] = true;
        }

        $gramOverlaps = [];
        if (count($candidateSet) < self::FUZZY_FALLBACK_THRESHOLD) {
            $trigramCandidates = $this->search->trigramCandidates(
                ProductSearchText::grams($normalizedQuery),
                self::CANDIDATE_POOL_LIMIT,
            );
            foreach ($trigramCandidates as $candidate) {
                $candidateSet[$candidate['id']] = true;
                $gramOverlaps[$candidate['id']] = $candidate['overlap'];
            }
        }

        $candidateIds = array_slice(array_keys($candidateSet), 0, self::CANDIDATE_POOL_LIMIT);
        if ($candidateIds === []) {
            return self::response([], $filters, $normalizedQuery, false);
        }

        $repositoryFilters = $filters;
        $repositoryFilters['page'] = 1;
        $repositoryFilters['perPage'] = self::CANDIDATE_POOL_LIMIT;
        if ($repositoryFilters['sort'] === 'relevance') {
            $repositoryFilters['sort'] = 'newest';
        }
        $result = $this->products->listCatalog($repositoryFilters, $candidateIds);
        $eligibleRows = $result['products'];
        if ($filters['sort'] === 'relevance') {
            usort(
                $eligibleRows,
                static function (array $left, array $right) use ($fullTextScores, $gramOverlaps): int {
                    $leftId = (int) $left['id'];
                    $rightId = (int) $right['id'];
                    $leftScore = ($fullTextScores[$leftId] ?? 0.0) * 5
                        + ($gramOverlaps[$leftId] ?? 0);
                    $rightScore = ($fullTextScores[$rightId] ?? 0.0) * 5
                        + ($gramOverlaps[$rightId] ?? 0);

                    return ($rightScore <=> $leftScore) ?: ($leftId <=> $rightId);
                },
            );
        }

        $capped = count($eligibleRows) >= self::CANDIDATE_LIMIT
            || count($candidateSet) >= self::CANDIDATE_POOL_LIMIT;
        $eligibleRows = array_slice($eligibleRows, 0, self::CANDIDATE_LIMIT);

        $rankedProducts = [];
        foreach ($eligibleRows as $row) {
            $productId = (int) $row['id'];
            $normalizedName = ProductSearchText::normalize((string) $row['name']);
            $similarity = ProductSearchText::similarity($normalizedQuery, $normalizedName);
            $fullTextScore = $fullTextScores[$productId] ?? 0.0;
            if ($fullTextScore <= 0 && $similarity < 0.35) {
                continue;
            }

            $rankedProducts[] = [
                'row' => $row,
                'score' => ($similarity * 100) + min(20, $fullTextScore * 5),
            ];
        }

        if ($filters['sort'] === 'relevance') {
            usort(
                $rankedProducts,
                static fn (array $left, array $right): int =>
                    ($right['score'] <=> $left['score'])
                    ?: ((int) $left['row']['id'] <=> (int) $right['row']['id']),
            );
        }

        $products = array_map(
            static fn (array $item): array => CatalogService::presentProduct($item['row']),
            $rankedProducts,
        );

        return self::response($products, $filters, $normalizedQuery, $capped);
    }

    /**
     * @param list<array<string, mixed>> $products
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|string|bool>}
     */
    private static function response(
        array $products,
        array $filters,
        string $normalizedQuery,
        bool $capped,
    ): array {
        $total = count($products);
        $pageProducts = array_slice(
            $products,
            ($filters['page'] - 1) * $filters['perPage'],
            $filters['perPage'],
        );

        return [
            'data' => $pageProducts,
            'meta' => [
                'q' => $normalizedQuery,
                'page' => $filters['page'],
                'perPage' => $filters['perPage'],
                'total' => $total,
                'sort' => $filters['sort'],
                'currency' => $filters['currency'],
                'candidateLimit' => self::CANDIDATE_LIMIT,
                'capped' => $capped,
            ],
        ];
    }
}
