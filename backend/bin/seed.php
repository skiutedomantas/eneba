<?php

declare(strict_types=1);

use App\Database\Connection;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::create();
$seedFiles = glob(dirname(__DIR__) . '/database/seeds/*.php');
if ($seedFiles === false) {
    throw new RuntimeException('Could not list database seed files.');
}
sort($seedFiles, SORT_STRING);

$pdo->beginTransaction();
foreach ($seedFiles as $seedFile) {
    $seed = require $seedFile;
    if (is_callable($seed)) {
        $seed($pdo);
        fwrite(STDOUT, 'Loaded: ' . basename($seedFile) . "\n");
        continue;
    }

    $statements = $seed;
    if (!is_array($statements)) {
        throw new RuntimeException('Each seed file must return SQL statements or a seed callback.');
    }

    foreach ($statements as $statement) {
        if (!is_string($statement) || trim($statement) === '') {
            throw new RuntimeException('A seed file contains an invalid SQL statement.');
        }
        $pdo->exec($statement);
    }
    fwrite(STDOUT, 'Loaded: ' . basename($seedFile) . "\n");
}
$pdo->commit();
