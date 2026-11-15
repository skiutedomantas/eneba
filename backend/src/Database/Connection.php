<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class Connection
{
    public static function create(): PDO
    {
        $database = getenv('DB_DATABASE');
        $username = getenv('DB_USERNAME');
        $driver = getenv('DB_DRIVER') ?: 'mysql';

        if ($database === false || $database === '' || $username === false || $username === '') {
            throw new RuntimeException('DB_DATABASE and DB_USERNAME must be configured.');
        }

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new RuntimeException('DB_DRIVER must be either mysql or pgsql.');
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
        $password = getenv('DB_PASSWORD') ?: '';
        if ($driver === 'pgsql') {
            $sslMode = getenv('DB_SSLMODE') ?: 'prefer';
            if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
                throw new RuntimeException('DB_SSLMODE is not a supported PostgreSQL SSL mode.');
            }
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                $host,
                $port,
                $database,
                $sslMode,
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $database,
            );
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec($driver === 'pgsql' ? "SET TIME ZONE 'UTC'" : "SET time_zone = '+00:00'");

        return $pdo;
    }
}
