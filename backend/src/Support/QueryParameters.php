<?php

declare(strict_types=1);

namespace App\Support;

final class QueryParameters
{
    /**
     * @param array<string, mixed> $query
     */
    public static function integer(
        array $query,
        string $name,
        int $default,
        int $minimum,
        int $maximum,
    ): int {
        if (!array_key_exists($name, $query)) {
            return $default;
        }

        $value = $query[$name];
        if (!is_string($value) || !preg_match('/^\d+$/D', $value)) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                [$name => 'Must be an integer.'],
            );
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < $minimum || $integer > $maximum) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                [$name => sprintf('Must be between %d and %d.', $minimum, $maximum)],
            );
        }

        return $integer;
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function optionalCode(array $query, string $name, int $maximumLength = 40): ?string
    {
        if (!array_key_exists($name, $query) || $query[$name] === '') {
            return null;
        }

        $value = $query[$name];
        if (
            !is_string($value)
            || strlen($value) > $maximumLength
            || !preg_match('/^[A-Za-z0-9-]+$/D', $value)
        ) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                [$name => 'Must contain only letters, numbers, or hyphens.'],
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function currency(array $query, string $default = 'EUR'): string
    {
        if (!array_key_exists('currency', $query)) {
            return $default;
        }

        $value = $query['currency'];
        if (!is_string($value) || !preg_match('/^[A-Za-z]{3}$/D', $value)) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                ['currency' => 'Must be a three-letter currency code.'],
            );
        }

        return strtoupper($value);
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function optionalPrice(array $query, string $name): ?int
    {
        if (!array_key_exists($name, $query) || $query[$name] === '') {
            return null;
        }

        $value = $query[$name];
        if (!is_string($value) || !preg_match('/^\d+$/D', $value)) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                [$name => 'Must be a non-negative integer in minor currency units.'],
            );
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 0) {
            throw new ValidationError(
                'One or more query parameters are invalid.',
                [$name => 'Must be a non-negative integer in minor currency units.'],
            );
        }

        return $integer;
    }
}
