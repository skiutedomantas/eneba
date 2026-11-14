<?php

declare(strict_types=1);

use App\Database\Connection;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::create();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
);

$lock = $pdo->query("SELECT GET_LOCK('gamehub_schema_migrations', 10)")->fetchColumn();
if ((int) $lock !== 1) {
    throw new RuntimeException('Could not acquire the database migration lock.');
}

try {
    $migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.php');
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

        foreach ($statements as $statement) {
            if (!is_string($statement) || trim($statement) === '') {
                throw new RuntimeException("Migration {$version} contains an invalid SQL statement.");
            }
            $pdo->exec($statement);
        }

        $markApplied->execute(['version' => $version]);
        fwrite(STDOUT, "Applied: {$version}\n");
    }
} finally {
    $pdo->query("SELECT RELEASE_LOCK('gamehub_schema_migrations')");
}
