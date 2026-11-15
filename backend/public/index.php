<?php

declare(strict_types=1);

$allowedOrigin = 'https://gamehub-frontend-e140.onrender.com';

if (isset($_SERVER['HTTP_ORIGIN']) &&
    $_SERVER['HTTP_ORIGIN'] === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Accept');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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
