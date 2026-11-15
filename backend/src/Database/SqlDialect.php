<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class SqlDialect
{
    public static function driver(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function isPostgreSQL(PDO $pdo): bool
    {
        return self::driver($pdo) === 'pgsql';
    }

    public static function binaryParameter(PDO $pdo, string $placeholder): string
    {
        return self::isPostgreSQL($pdo)
            ? "decode({$placeholder}, 'base64')"
            : $placeholder;
    }

    public static function binaryParameterValue(PDO $pdo, string $value): string
    {
        return self::isPostgreSQL($pdo) ? base64_encode($value) : $value;
    }

    public static function binaryColumn(PDO $pdo, string $column): string
    {
        return self::isPostgreSQL($pdo)
            ? "encode({$column}, 'base64')"
            : $column;
    }

    public static function binaryColumnValue(PDO $pdo, mixed $value): string
    {
        if (!is_string($value)) {
            throw new RuntimeException('The database returned an invalid binary value.');
        }
        if (!self::isPostgreSQL($pdo)) {
            return $value;
        }

        $decoded = base64_decode($value, true);
        if (!is_string($decoded)) {
            throw new RuntimeException('The database returned an invalid encoded binary value.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function insertId(PDO $pdo, string $sql, array $parameters = []): int
    {
        $statement = $pdo->prepare(
            self::isPostgreSQL($pdo) ? rtrim($sql, " \t\n\r\0\x0B;") . ' RETURNING id' : $sql,
        );
        $statement->execute($parameters);
        $id = self::isPostgreSQL($pdo) ? $statement->fetchColumn() : $pdo->lastInsertId();
        $integer = filter_var($id, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 1) {
            throw new RuntimeException('The database did not return a valid inserted row ID.');
        }

        return $integer;
    }

    public static function isUniqueViolation(PDOException $exception): bool
    {
        if (($exception->errorInfo[0] ?? $exception->getCode()) === '23505') {
            return true;
        }

        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    public static function isRetryableTransactionFailure(PDOException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if (in_array($sqlState, ['40P01', '40001', '55P03'], true)) {
            return true;
        }

        return in_array((int) ($exception->errorInfo[1] ?? 0), [1205, 1213], true);
    }

    public static function timestampDaysAgo(int $days): string
    {
        if ($days < 0) {
            throw new RuntimeException('A timestamp offset cannot be negative.');
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s.u');
    }

    public static function timestampSecondsAgo(int $seconds): string
    {
        if ($seconds < 0) {
            throw new RuntimeException('A timestamp offset cannot be negative.');
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("-{$seconds} seconds")
            ->format('Y-m-d H:i:s.u');
    }

    public static function timestampAfterSeconds(int $seconds): string
    {
        if ($seconds < 0) {
            throw new RuntimeException('A timestamp offset cannot be negative.');
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("+{$seconds} seconds")
            ->format('Y-m-d H:i:s.u');
    }
}
