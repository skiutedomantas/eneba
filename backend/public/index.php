<?php

declare(strict_types=1);

use App\Support\JsonResponse;

$requestId = bin2hex(random_bytes(16));
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
header('X-Request-Id: ' . $requestId);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

set_exception_handler(static function (\Throwable $exception) use ($requestId): void {
    error_log(json_encode([
        'requestId' => $requestId,
        'exception' => $exception::class,
    ], JSON_UNESCAPED_SLASHES));

    JsonResponse::send(500, [
        'error' => [
            'code' => 'INTERNAL_SERVER_ERROR',
            'message' => 'An unexpected error occurred.',
            'requestId' => $requestId,
        ],
    ]);
});

require dirname(__DIR__) . '/bootstrap.php';

require dirname(__DIR__) . '/routes/api.php';
