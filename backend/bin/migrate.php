<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\SqlDialect;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::create();
$driver = SqlDialect::driver($pdo);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )',
);

$migrationDirectory = dirname(__DIR__) . '/database/migrations';
if ($driver === 'pgsql') {
    $migrationDirectory .= '/postgresql';
    $lockAcquired = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $lockResult = $pdo->query(
            'SELECT pg_try_advisory_lock(1196647237, 1)',
        )->fetchColumn();
        $lockAcquired = in_array($lockResult, [true, 't', 1, '1'], true);
        if ($lockAcquired) {
            break;
        }
        usleep(100_000);
    }
} else {
    $lockAcquired = (int) $pdo->query(
        "SELECT GET_LOCK('gamehub_schema_migrations', 10)",
    )->fetchColumn() === 1;
}
if (!$lockAcquired) {
    throw new RuntimeException('Could not acquire the database migration lock.');
}

try {
    $migrationFiles = glob($migrationDirectory . '/*.php');
    if ($migrationFiles === false) {
        throw new RuntimeException('Could not list database migrations.');
    }
    sort($migrationFiles, SORT_STRING);

    $isApplied = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
    $markApplied = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');

    foreach ($migrationFiles as $migrationFile) {
        $version = basename($migrationFile);
        $isApplied->execute(['version' => $version]);
        if ($isApplied->fetchColumn() !== false) {
            fwrite(STDOUT, "Already applied: {$version}\n");
            continue;
        }

        $statements = require $migrationFile;
        if (!is_array($statements)) {
            throw new RuntimeException("Migration {$version} must return an array of SQL statements.");
        }

        if ($driver === 'pgsql') {
            $pdo->beginTransaction();
        }
        try {
            foreach ($statements as $statement) {
                if (!is_string($statement) || trim($statement) === '') {
                    throw new RuntimeException("Migration {$version} contains an invalid SQL statement.");
                }
                $pdo->exec($statement);
            }

            $markApplied->execute(['version' => $version]);
            if ($driver === 'pgsql') {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($driver === 'pgsql' && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        fwrite(STDOUT, "Applied: {$version}\n");
    }
} finally {
    if ($driver === 'pgsql') {
        $pdo->query('SELECT pg_advisory_unlock(1196647237, 1)');
    } else {
        $pdo->query("SELECT RELEASE_LOCK('gamehub_schema_migrations')");
    }
}
