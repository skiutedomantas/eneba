<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Support\JsonResponse;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'GET' && $path === '/api/v1/health') {
    try {
        Connection::create()->query('SELECT 1')->fetchColumn();
    } catch (\PDOException $exception) {
        error_log(json_encode([
            'requestId' => $requestId,
            'exception' => $exception::class,
        ], JSON_UNESCAPED_SLASHES));

        JsonResponse::send(503, [
            'error' => [
                'code' => 'SERVICE_UNAVAILABLE',
                'message' => 'The database is unavailable.',
                'requestId' => $requestId,
            ],
        ]);
    }

    JsonResponse::send(200, [
        'data' => [
            'status' => 'ok',
            'database' => 'ok',
            'requestId' => $requestId,
        ],
    ]);
}

JsonResponse::send(404, [
    'error' => [
        'code' => 'NOT_FOUND',
        'message' => 'The requested API resource was not found.',
        'requestId' => $requestId,
    ],
]);
