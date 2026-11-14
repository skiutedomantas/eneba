<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LookupRepository;
use App\Support\ValidationError;

final class LookupService
{
    public function __construct(private readonly LookupRepository $lookups)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    public function categories(array $query): array
    {
        self::assertNoQuery($query);
        return ['data' => $this->lookups->categories(), 'meta' => ['limit' => 100]];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    public function platforms(array $query): array
    {
        self::assertNoQuery($query);
        return ['data' => $this->lookups->platforms(), 'meta' => ['limit' => 100]];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int}}
     */
    public function regions(array $query): array
    {
        self::assertNoQuery($query);
        return ['data' => $this->lookups->regions(), 'meta' => ['limit' => 100]];
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function assertNoQuery(array $query): void
    {
        if ($query !== []) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['query' => 'This endpoint does not accept query parameters.'],
            );
        }
    }
}
