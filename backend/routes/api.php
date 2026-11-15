<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\AuthRepository;
use App\Repositories\CartRepository;
use App\Repositories\HomeRepository;
use App\Repositories\LookupRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductSearchRepository;
use App\Services\CatalogService;
use App\Services\AuthService;
use App\Services\CartService;
use App\Services\HomeService;
use App\Services\LookupService;
use App\Services\ProductDetailService;
use App\Services\SearchService;
use App\Support\JsonResponse;
use App\Support\ApiException;
use App\Support\JsonRequest;
use App\Support\ValidationError;
use App\Security\SessionCookies;

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

$authAndCartRoutes = [
    '/api/v1/auth/csrf' => ['GET'],
    '/api/v1/auth/register' => ['POST'],
    '/api/v1/auth/login' => ['POST'],
    '/api/v1/auth/logout' => ['POST'],
    '/api/v1/auth/me' => ['GET'],
    '/api/v1/cart' => ['GET'],
    '/api/v1/cart/items' => ['POST'],
];
$cartItemMatch = [];
$isCartItemPath = is_string($path)
    && preg_match('~^/api/v1/cart/items/([1-9][0-9]*)$~D', $path, $cartItemMatch) === 1;
$cartItemMethods = ['PATCH', 'DELETE'];
$isAuthOrCartPath = isset($authAndCartRoutes[$path]) || $isCartItemPath;

if ($isAuthOrCartPath) {
    $allowedMethods = $isCartItemPath
        ? $cartItemMethods
        : $authAndCartRoutes[$path];
    if (!in_array($method, $allowedMethods, true)) {
        header('Allow: ' . implode(', ', $allowedMethods));
        JsonResponse::send(405, [
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'This method is not supported for the requested resource.',
                'requestId' => $requestId,
            ],
        ]);
    }

    try {
        $pdo = Connection::create();
        $cart = new CartService($pdo, new CartRepository($pdo));
        $auth = new AuthService($pdo, new AuthRepository($pdo), $cart);
        $sessionToken = SessionCookies::sessionToken();
        $guestCartToken = SessionCookies::guestCartToken();
        $session = $auth->session($sessionToken);

        if ($path === '/api/v1/auth/csrf') {
            $csrfToken = $auth->issueCsrfToken($session, SessionCookies::csrfToken());
            SessionCookies::setCsrf($csrfToken, time() + (14 * 24 * 60 * 60));
            JsonResponse::send(200, ['data' => ['csrfToken' => $csrfToken]]);
        }

        if ($path === '/api/v1/auth/register' || $path === '/api/v1/auth/login') {
            $auth->assertCsrf(
                $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
                SessionCookies::csrfToken(),
                null,
            );
            $input = JsonRequest::body();
            $result = $path === '/api/v1/auth/register'
                ? $auth->register(
                    $input,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $sessionToken,
                    $guestCartToken,
                )
                : $auth->login(
                    $input,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $sessionToken,
                    $guestCartToken,
                );
            SessionCookies::clearGuestCart();
            SessionCookies::setSession(
                $result['sessionToken'],
                time() + (14 * 24 * 60 * 60),
            );
            SessionCookies::setCsrf(
                $result['csrfToken'],
                time() + (14 * 24 * 60 * 60),
            );
            JsonResponse::send(
                $path === '/api/v1/auth/register' ? 201 : 200,
                [
                    'data' => [
                        'user' => $result['user'],
                        'csrfToken' => $result['csrfToken'],
                    ],
                ],
            );
        }

        if ($path === '/api/v1/auth/me') {
            if ($session === null) {
                throw new ApiException(401, 'UNAUTHENTICATED', 'Sign in to continue.');
            }
            JsonResponse::send(200, ['data' => ['user' => $auth->presentSessionUser($session)]]);
        }

        if ($path === '/api/v1/auth/logout') {
            if ($session === null) {
                throw new ApiException(401, 'UNAUTHENTICATED', 'Sign in to continue.');
            }
            $auth->assertCsrf(
                $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
                SessionCookies::csrfToken(),
                $session,
            );
            $auth->logout($sessionToken);
            SessionCookies::clear();
            JsonResponse::send(200, ['data' => ['loggedOut' => true]]);
        }

        if ($path === '/api/v1/cart' && $method === 'GET') {
            JsonResponse::send(
                200,
                $session === null
                    ? $cart->getGuest($guestCartToken)
                    : $cart->get($session['user_id']),
            );
        }

        if ($session === null && $path !== '/api/v1/cart/items' && !$isCartItemPath) {
            throw new ApiException(401, 'UNAUTHENTICATED', 'Sign in to continue.');
        }

        if ($path === '/api/v1/cart/items' && $method === 'POST') {
            $auth->assertCsrf(
                $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
                SessionCookies::csrfToken(),
                $session,
            );
            $input = JsonRequest::body();
            if ($session !== null) {
                JsonResponse::send(201, $cart->add($session['user_id'], $input));
            }

            if ($guestCartToken === null) {
                $guestCartToken = bin2hex(random_bytes(32));
                SessionCookies::setGuestCart(
                    $guestCartToken,
                    time() + (14 * 24 * 60 * 60),
                );
            }
            JsonResponse::send(201, $cart->addGuest($guestCartToken, $input));
        }

        if ($isCartItemPath) {
            $auth->assertCsrf(
                $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
                SessionCookies::csrfToken(),
                $session,
            );
            $itemId = filter_var($cartItemMatch[1], FILTER_VALIDATE_INT);
            if ($itemId === false) {
                throw new ValidationError(
                    'One or more path parameters are invalid.',
                    ['id' => 'Must be a positive integer.'],
                );
            }
            if ($session === null && $guestCartToken === null) {
                throw new ApiException(
                    404,
                    'CART_ITEM_NOT_FOUND',
                    'The cart item was not found.',
                );
            }
            if ($method === 'PATCH') {
                $response = $session === null
                    ? $cart->updateGuest(
                        $guestCartToken,
                        $itemId,
                        JsonRequest::body(),
                    )
                    : $cart->update(
                        $session['user_id'],
                        $itemId,
                        JsonRequest::body(),
                    );
                JsonResponse::send(
                    200,
                    $response,
                );
            }
            $response = $session === null
                ? $cart->removeGuest($guestCartToken, $itemId)
                : $cart->remove($session['user_id'], $itemId);
            JsonResponse::send(200, $response);
        }
    } catch (ValidationError $exception) {
        JsonResponse::send(422, [
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $exception->getMessage(),
                'fields' => $exception->fields,
                'requestId' => $requestId,
            ],
        ]);
    } catch (ApiException $exception) {
        $error = [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
            'requestId' => $requestId,
        ];
        if ($exception->fields !== []) {
            $error['fields'] = $exception->fields;
        }
        JsonResponse::send($exception->status, ['error' => $error]);
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

$getEndpoints = [
    '/api/v1/products',
    '/api/v1/search',
    '/api/v1/categories',
    '/api/v1/platforms',
    '/api/v1/regions',
    '/api/v1/home/hero-banners',
    '/api/v1/home/best-sellers',
    '/api/v1/home/special-deals',
    '/api/v1/home/popular-categories',
];

$productDetailMatch = [];
$isProductDetailPath = is_string($path)
    && preg_match('~^/api/v1/products/([a-z0-9]+(?:-[a-z0-9]+)*)$~D', $path, $productDetailMatch) === 1;

if ($method === 'GET' && (in_array($path, $getEndpoints, true) || $isProductDetailPath)) {
    try {
        $pdo = Connection::create();
        if ($isProductDetailPath) {
            $response = (new ProductDetailService(
                new ProductRepository($pdo),
            ))->getBySlug($productDetailMatch[1]);
            if ($response === null) {
                JsonResponse::send(404, [
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'The requested product was not found.',
                        'requestId' => $requestId,
                    ],
                ]);
            }
            JsonResponse::send(200, $response);
        }

        $response = match ($path) {
            '/api/v1/products' => (new CatalogService(
                new ProductRepository($pdo),
            ))->listProducts($_GET),
            '/api/v1/search' => (new SearchService(
                new ProductRepository($pdo),
                new ProductSearchRepository($pdo),
            ))->search($_GET),
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

if (in_array($path, $getEndpoints, true) || $isProductDetailPath) {
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
