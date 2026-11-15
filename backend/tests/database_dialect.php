<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\SqlDialect;

require dirname(__DIR__) . '/bootstrap.php';

function expectDatabaseDialect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Connection::create();
$configuredDriver = getenv('DB_DRIVER') ?: 'mysql';
expectDatabaseDialect(
    SqlDialect::driver($pdo) === $configuredDriver,
    'PDO should connect using the explicitly configured database driver.',
);

$binary = random_bytes(64);
$storedBinary = SqlDialect::binaryParameterValue($pdo, $binary);
expectDatabaseDialect(
    hash_equals($binary, SqlDialect::binaryColumnValue($pdo, $storedBinary)),
    'Database-specific binary encoding should round-trip encrypted byte strings.',
);
expectDatabaseDialect(
    str_contains(
        SqlDialect::binaryParameter($pdo, ':ciphertext'),
        $configuredDriver === 'pgsql' ? "decode(:ciphertext, 'base64')" : ':ciphertext',
    ),
    'Binary parameters should use the selected database representation.',
);

$pdo->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(8));
    $email = "dialect-{$suffix}@example.test";
    $userId = SqlDialect::insertId(
        $pdo,
        'INSERT INTO users (email, password_hash, display_name)
         VALUES (:email, :password_hash, :display_name)',
        [
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'display_name' => 'Dialect Test',
        ],
    );
    $find = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $find->execute(['email' => $email]);
    expectDatabaseDialect(
        (int) $find->fetchColumn() === $userId,
        'Inserted row IDs should be returned consistently by both PDO drivers.',
    );
} finally {
    $pdo->rollBack();
}

fwrite(STDOUT, "Database dialect and binary/insert-ID checks passed.\n");
