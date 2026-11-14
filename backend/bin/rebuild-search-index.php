<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Services\ProductSearchIndexService;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::create();
$pdo->beginTransaction();

try {
    $indexedGrams = ProductSearchIndexService::rebuild($pdo);
    $pdo->commit();
    fwrite(STDOUT, "Rebuilt product search index with {$indexedGrams} grams.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
