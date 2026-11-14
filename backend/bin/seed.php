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
    $statements = require $seedFile;
    if (!is_array($statements)) {
        throw new RuntimeException('Each seed file must return an array of SQL statements.');
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
