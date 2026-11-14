<?php

declare(strict_types=1);

use App\Support\JsonResponse;

$requestId = bin2hex(random_bytes(16));
header('X-Request-Id: ' . $requestId);

require dirname(__DIR__) . '/bootstrap.php';

set_exception_handler(static function (\Throwable $exception) use ($requestId): void {
    error_log(json_encode([
        'requestId' => $requestId,
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES));

    JsonResponse::send(500, [
        'error' => [
            'code' => 'INTERNAL_SERVER_ERROR',
            'message' => 'An unexpected error occurred.',
            'requestId' => $requestId,
        ],
    ]);
});

require dirname(__DIR__) . '/routes/api.php';
