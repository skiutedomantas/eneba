<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Security\DigitalKeyCipher;
use App\Support\ApiException;
use App\Support\ValidationError;
use PDO;
use PDOException;
use Throwable;

final class CheckoutService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly OrderRepository $orders,
        private readonly DigitalKeyCipher $cipher,
    ) {
    }

    /**
     * @return array{httpStatus: int, data: array<string, int|string>}
     */
    public function checkout(int $userId, ?string $idempotencyKey, string $requestId): array
    {
        $idempotencyKey = self::idempotencyKey($idempotencyKey);

        try {
            $result = $this->transaction(function () use ($userId, $idempotencyKey): array {
                $existingOrderId = $this->orders->orderIdForIdempotency($userId, $idempotencyKey);
                if ($existingOrderId !== null) {
                    return [
                        'httpStatus' => 200,
                        'data' => $this->orderResponse($userId, $existingOrderId),
                    ];
                }

                $cartId = $this->orders->lockCartId($userId);
                if ($cartId === null) {
                    throw new ApiException(409, 'CART_EMPTY', 'Add an offer to your cart before checkout.');
                }
                $existingOrderId = $this->orders->orderIdForIdempotency(
                    $userId,
                    $idempotencyKey,
                    true,
                );
                if ($existingOrderId !== null) {
                    return [
                        'httpStatus' => 200,
                        'data' => $this->orderResponse($userId, $existingOrderId),
                    ];
                }

                $cartItems = $this->orders->cartItemsForUpdate($cartId);
                if ($cartItems === []) {
                    throw new ApiException(409, 'CART_EMPTY', 'Add an offer to your cart before checkout.');
                }

                $preparedItems = [];
                $currency = null;
                $totalMinor = 0;
                foreach ($cartItems as $cartItem) {
                    if ($cartItem['quantity'] < 1 || $cartItem['quantity'] > 99) {
                        throw new ApiException(
                            409,
                            'CART_ITEM_INVALID',
                            'A cart quantity is invalid. Your cart was kept unchanged.',
                        );
                    }
                    $offer = $this->orders->offerForCheckout($cartItem['offer_id'], true);
                    if ($offer === null || !self::offerIsActive($offer)) {
                        throw new ApiException(
                            409,
                            'OFFER_UNAVAILABLE',
                            'A selected offer is no longer available. Your cart was kept unchanged.',
                        );
                    }

                    $offerCurrency = (string) $offer['currency'];
                    if ($currency !== null && $currency !== $offerCurrency) {
                        throw new ApiException(
                            409,
                            'MIXED_CURRENCY_CART',
                            'Checkout requires all cart items to use the same currency. Your cart was kept unchanged.',
                        );
                    }
                    $currency = $offerCurrency;

                    $price = self::databaseInteger($offer['price_minor'], 'offer price');
                    $quantity = $cartItem['quantity'];
                    if ($price < 0 || $price > intdiv(PHP_INT_MAX, $quantity)) {
                        throw new ApiException(
                            409,
                            'ORDER_TOTAL_TOO_LARGE',
                            'The order total is outside the supported range. Your cart was kept unchanged.',
                        );
                    }
                    $lineTotal = $price * $quantity;
                    if ($totalMinor > PHP_INT_MAX - $lineTotal) {
                        throw new ApiException(
                            409,
                            'ORDER_TOTAL_TOO_LARGE',
                            'The order total is outside the supported range. Your cart was kept unchanged.',
                        );
                    }
                    $totalMinor += $lineTotal;

                    $keys = $this->orders->availableKeysForUpdate(
                        $cartItem['offer_id'],
                        $quantity,
                    );
                    if (count($keys) !== $quantity) {
                        throw new ApiException(
                            409,
                            'INSUFFICIENT_INVENTORY',
                            'There are not enough digital keys for a selected offer. Your cart was kept unchanged.',
                        );
                    }
                    foreach ($keys as $key) {
                        $this->cipher->verify(
                            $key['ciphertext'],
                            $key['nonce'],
                            $key['key_version'],
                            $cartItem['offer_id'],
                        );
                    }

                    $preparedItems[] = [
                        'offerId' => $cartItem['offer_id'],
                        'quantity' => $quantity,
                        'priceMinor' => $price,
                        'productName' => (string) $offer['product_name'],
                        'sellerName' => (string) $offer['seller_name'],
                        'platformName' => (string) $offer['platform_name'],
                        'regionName' => (string) $offer['region_name'],
                        'keyIds' => array_column($keys, 'id'),
                    ];
                }

                if ($currency === null) {
                    throw new ApiException(409, 'CART_EMPTY', 'Add an offer to your cart before checkout.');
                }

                $orderId = $this->orders->createPaidOrder(
                    $userId,
                    $currency,
                    $totalMinor,
                    $idempotencyKey,
                );
                foreach ($preparedItems as $item) {
                    $orderItemId = $this->orders->createOrderItem(
                        $orderId,
                        $item['offerId'],
                        $item['productName'],
                        $item['sellerName'],
                        $item['platformName'],
                        $item['regionName'],
                        $item['quantity'],
                        $item['priceMinor'],
                        $currency,
                    );
                    $this->orders->assignKeys($item['keyIds'], $orderItemId);
                }

                $this->orders->clearCart($cartId);

                return [
                    'httpStatus' => 201,
                    'data' => $this->orderResponse($userId, $orderId),
                ];
            });
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $existingOrderId = $this->orders->orderIdForIdempotency($userId, $idempotencyKey);
                if ($existingOrderId !== null) {
                    $result = [
                        'httpStatus' => 200,
                        'data' => $this->orderResponse($userId, $existingOrderId),
                    ];
                    self::logOutcome($requestId, 'replayed', $existingOrderId, null);
                    return $result;
                }
            }
            throw $exception;
        } catch (ApiException|ValidationError $exception) {
            self::logOutcome($requestId, 'failed', null, $exception instanceof ApiException
                ? $exception->errorCode
                : 'VALIDATION_ERROR');
            throw $exception;
        }

        self::logOutcome(
            $requestId,
            $result['httpStatus'] === 201 ? 'completed' : 'replayed',
            $result['data']['orderId'],
            null,
        );
        return $result;
    }

    /**
     * @return array{orderId: int, status: string, currency: string, subtotalMinor: int, totalMinor: int}
     */
    private function orderResponse(int $userId, int $orderId): array
    {
        $order = $this->orders->orderForUser($userId, $orderId);
        if ($order === null) {
            throw new \RuntimeException('The completed order could not be loaded.');
        }

        return [
            'orderId' => (int) $order['id'],
            'status' => (string) $order['status'],
            'currency' => (string) $order['currency'],
            'subtotalMinor' => self::databaseInteger($order['subtotal_minor'], 'order subtotal'),
            'totalMinor' => self::databaseInteger($order['total_minor'], 'order total'),
        ];
    }

    /**
     * @param array<string, mixed> $offer
     */
    private static function offerIsActive(array $offer): bool
    {
        return $offer['offer_status'] === 'active'
            && $offer['product_status'] === 'active'
            && $offer['seller_status'] === 'approved'
            && $offer['platform_status'] === 'active'
            && $offer['region_status'] === 'active';
    }

    private static function idempotencyKey(?string $value): string
    {
        if (
            $value === null
            || strlen($value) < 8
            || strlen($value) > 128
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1
        ) {
            throw new ValidationError('One or more request headers are invalid.', [
                'Idempotency-Key' => 'Provide 8 to 128 letters, numbers, periods, underscores, colons, or hyphens.',
            ]);
        }

        return $value;
    }

    private static function databaseInteger(mixed $value, string $field): int
    {
        if (!is_int($value) && (!is_string($value) || preg_match('/^\d+$/D', $value) !== 1)) {
            throw new \RuntimeException("The database returned an invalid {$field}.");
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false) {
            throw new ApiException(
                409,
                'ORDER_TOTAL_TOO_LARGE',
                'The order total is outside the supported range. Your cart was kept unchanged.',
            );
        }

        return $integer;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        for ($attempt = 0; ; $attempt++) {
            $ownsTransaction = !$this->pdo->inTransaction();
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }

            try {
                $result = $callback();
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return $result;
            } catch (PDOException $exception) {
                if ($ownsTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $driverCode = $exception->errorInfo[1] ?? null;
                if (
                    $ownsTransaction
                    && $attempt < 2
                    && in_array($driverCode, [1205, 1213], true)
                ) {
                    continue;
                }
                throw $exception;
            } catch (Throwable $exception) {
                if ($ownsTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
        }
    }

    private static function logOutcome(
        string $requestId,
        string $outcome,
        ?int $orderId,
        ?string $errorCode,
    ): void {
        error_log(json_encode([
            'event' => 'checkout.' . $outcome,
            'requestId' => $requestId,
            'orderId' => $orderId,
            'errorCode' => $errorCode,
        ], JSON_UNESCAPED_SLASHES));
    }
}
