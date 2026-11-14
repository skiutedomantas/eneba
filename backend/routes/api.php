<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\HomeRepository;
use App\Repositories\LookupRepository;
use App\Repositories\ProductRepository;
use App\Support\JsonResponse;
use App\Support\ValidationError;
use App\Services\CatalogService;
use App\Services\HomeService;
use App\Services\LookupService;

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
    } catch (\RuntimeException $exception) {
        error_log(json_encode([
            'requestId' => $requestId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_SLASHES));

        JsonResponse::send(503, [
            'error' => [
                'code' => 'DATABASE_CONFIGURATION_ERROR',
                'message' => 'The database is not configured.',
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

$getEndpoints = [
    '/api/v1/products',
    '/api/v1/categories',
    '/api/v1/platforms',
    '/api/v1/regions',
    '/api/v1/home/hero-banners',
    '/api/v1/home/best-sellers',
    '/api/v1/home/special-deals',
    '/api/v1/home/popular-categories',
];

if ($method === 'GET' && in_array($path, $getEndpoints, true)) {
    try {
        $pdo = Connection::create();
        $response = match ($path) {
            '/api/v1/products' => (new CatalogService(
                new ProductRepository($pdo),
            ))->listProducts($_GET),
            '/api/v1/categories' => (new LookupService(
                new LookupRepository($pdo),
            ))->categories($_GET),
            '/api/v1/platforms' => (new LookupService(
                new LookupRepository($pdo),
            ))->platforms($_GET),
            '/api/v1/regions' => (new LookupService(
                new LookupRepository($pdo),
            ))->regions($_GET),
            '/api/v1/home/hero-banners' => (new HomeService(
                new HomeRepository($pdo),
            ))->heroBanners($_GET),
            '/api/v1/home/best-sellers' => (new HomeService(
                new HomeRepository($pdo),
            ))->bestSellers($_GET),
            '/api/v1/home/special-deals' => (new HomeService(
                new HomeRepository($pdo),
            ))->specialDeals($_GET),
            '/api/v1/home/popular-categories' => (new HomeService(
                new HomeRepository($pdo),
            ))->popularCategories($_GET),
            default => throw new \LogicException('The matched route has no handler.'),
        };
        JsonResponse::send(200, $response);
    } catch (ValidationError $exception) {
        JsonResponse::send(422, [
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $exception->getMessage(),
                'fields' => $exception->fields,
                'requestId' => $requestId,
            ],
        ]);
    } catch (\PDOException $exception) {
        error_log(json_encode([
            'requestId' => $requestId,
            'exception' => $exception::class,
        ], JSON_UNESCAPED_SLASHES));

        JsonResponse::send(503, [
            'error' => [
                'code' => 'SERVICE_UNAVAILABLE',
                'message' => 'The service is temporarily unavailable.',
                'requestId' => $requestId,
            ],
        ]);
    } catch (\RuntimeException $exception) {
        error_log(json_encode([
            'requestId' => $requestId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_SLASHES));

        JsonResponse::send(503, [
            'error' => [
                'code' => 'SERVICE_UNAVAILABLE',
                'message' => 'The service is not configured.',
                'requestId' => $requestId,
            ],
        ]);
    }
}

if (in_array($path, $getEndpoints, true)) {
    header('Allow: GET');
    JsonResponse::send(405, [
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'This endpoint only supports GET requests.',
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
