<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CartRepository;
use App\Support\ApiException;
use App\Support\ValidationError;
use PDO;
use Throwable;

final class CartService
{
    private const MAX_QUANTITY = 99;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CartRepository $carts,
    ) {
    }

    /**
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function get(int $userId): array
    {
        $cartId = $this->carts->userCartId($userId);
        return $this->getByCartId($cartId);
    }

    /**
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function getGuest(?string $guestToken): array
    {
        $cartId = $guestToken === null
            ? null
            : $this->carts->guestCartId(self::guestTokenHash($guestToken));

        return $this->getByCartId($cartId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    private function getByCartId(?int $cartId): array
    {
        $rows = $cartId === null ? [] : $this->carts->items($cartId);
        $items = [];
        $totals = [];
        $itemCount = 0;

        foreach ($rows as $row) {
            $quantity = (int) $row['quantity'];
            $itemCount += $quantity;
            $active = self::offerIsActive($row);
            $price = $active ? (int) $row['price_minor'] : null;
            $currency = $active ? (string) $row['currency'] : null;
            $lineTotal = $price === null ? null : self::multiplyPrice($price, $quantity);

            if ($lineTotal !== null && $currency !== null) {
                $currentTotal = $totals[$currency] ?? 0;
                if ($currentTotal > PHP_INT_MAX - $lineTotal) {
                    throw new ApiException(
                        409,
                        'CART_TOTAL_TOO_LARGE',
                        'The cart total is outside the supported range.',
                    );
                }
                $totals[$currency] = $currentTotal + $lineTotal;
            }

            $items[] = [
                'id' => (int) $row['id'],
                'offerId' => (int) $row['offer_id'],
                'product' => [
                    'slug' => (string) $row['product_slug'],
                    'name' => (string) $row['product_name'],
                ],
                'seller' => ['name' => (string) $row['seller_name']],
                'platform' => ['name' => (string) $row['platform_name']],
                'region' => ['name' => (string) $row['region_name']],
                'quantity' => $quantity,
                'offerAvailable' => $active,
                'unavailableReason' => $active
                    ? null
                    : 'This offer is no longer available. Remove it from your cart.',
                'unitPriceMinor' => $price,
                'lineTotalMinor' => $lineTotal,
                'currency' => $currency,
            ];
        }

        $formattedTotals = [];
        foreach ($totals as $currency => $subtotalMinor) {
            $formattedTotals[] = [
                'currency' => $currency,
                'subtotalMinor' => $subtotalMinor,
            ];
        }

        return [
            'data' => [
                'items' => $items,
                'totals' => $formattedTotals,
                'itemCount' => $itemCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function add(int $userId, array $input): array
    {
        return $this->addToCart(
            fn (bool $create, bool $lock): ?int => $this->carts->userCartId(
                $userId,
                $create,
                $lock,
            ),
            $input,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function addGuest(string $guestToken, array $input): array
    {
        $guestTokenHash = self::guestTokenHash($guestToken);
        return $this->addToCart(
            fn (bool $create, bool $lock): ?int => $this->carts->guestCartId(
                $guestTokenHash,
                $create,
                $lock,
            ),
            $input,
        );
    }

    /**
     * @param callable(bool, bool): ?int $cartIdResolver
     * @param array<string, mixed> $input
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    private function addToCart(callable $cartIdResolver, array $input): array
    {
        self::assertFields($input, ['offerId', 'quantity']);
        $offerId = self::positiveInteger($input['offerId'] ?? null, 'offerId');
        $quantity = self::quantity($input['quantity'] ?? null);
        $cartId = null;

        $this->transaction(function () use (
            $cartIdResolver,
            &$cartId,
            $offerId,
            $quantity,
        ): void {
            $cartId = $cartIdResolver(true, true);
            if ($cartId === null) {
                throw new \RuntimeException('The cart could not be created.');
            }
            $this->assertOfferAvailable($offerId, true);

            $existing = $this->carts->itemIdForOffer($cartId, $offerId, true);
            if ($existing === null) {
                $this->carts->insertItem($cartId, $offerId, $quantity);
                return;
            }

            $nextQuantity = $existing['quantity'] + $quantity;
            if ($nextQuantity > self::MAX_QUANTITY) {
                throw new ValidationError(
                    'One or more fields are invalid.',
                    ['quantity' => sprintf('The cart limit is %d per offer.', self::MAX_QUANTITY)],
                );
            }
            $this->carts->setQuantity($existing['id'], $nextQuantity);
        });

        return $this->getByCartId($cartId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function update(int $userId, int $itemId, array $input): array
    {
        return $this->updateCartItem(
            fn (bool $lock): ?int => $this->carts->userCartId($userId, false, $lock),
            $itemId,
            $input,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function updateGuest(string $guestToken, int $itemId, array $input): array
    {
        $guestTokenHash = self::guestTokenHash($guestToken);
        return $this->updateCartItem(
            fn (bool $lock): ?int => $this->carts->guestCartId(
                $guestTokenHash,
                false,
                $lock,
            ),
            $itemId,
            $input,
        );
    }

    /**
     * @param callable(bool): ?int $cartIdResolver
     * @param array<string, mixed> $input
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    private function updateCartItem(
        callable $cartIdResolver,
        int $itemId,
        array $input,
    ): array {
        self::assertFields($input, ['quantity']);
        $quantity = self::quantity($input['quantity'] ?? null);
        $cartId = null;

        $this->transaction(function () use (
            $cartIdResolver,
            &$cartId,
            $itemId,
            $quantity,
        ): void {
            $cartId = $cartIdResolver(true);
            $item = $cartId === null ? null : $this->carts->item($cartId, $itemId, true);
            if ($item === null) {
                throw new ApiException(404, 'CART_ITEM_NOT_FOUND', 'The cart item was not found.');
            }

            $this->assertOfferAvailable($item['offer_id'], true);
            $this->carts->setQuantity($itemId, $quantity);
        });

        return $this->getByCartId($cartId);
    }

    /**
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function remove(int $userId, int $itemId): array
    {
        return $this->removeCartItem(
            fn (bool $lock): ?int => $this->carts->userCartId($userId, false, $lock),
            $itemId,
        );
    }

    /**
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    public function removeGuest(string $guestToken, int $itemId): array
    {
        $guestTokenHash = self::guestTokenHash($guestToken);
        return $this->removeCartItem(
            fn (bool $lock): ?int => $this->carts->guestCartId(
                $guestTokenHash,
                false,
                $lock,
            ),
            $itemId,
        );
    }

    /**
     * @param callable(bool): ?int $cartIdResolver
     * @return array{
     *   data: array{
     *     items: list<array<string, mixed>>,
     *     totals: list<array{currency: string, subtotalMinor: int}>,
     *     itemCount: int
     *   }
     * }
     */
    private function removeCartItem(callable $cartIdResolver, int $itemId): array
    {
        $cartId = null;
        $this->transaction(function () use ($cartIdResolver, &$cartId, $itemId): void {
            $cartId = $cartIdResolver(true);
            $item = $cartId === null ? null : $this->carts->item($cartId, $itemId, true);
            if ($item === null) {
                throw new ApiException(404, 'CART_ITEM_NOT_FOUND', 'The cart item was not found.');
            }
            $this->carts->deleteItem($itemId);
        });

        return $this->getByCartId($cartId);
    }

    public function mergeGuestCart(int $userId, string $guestToken): void
    {
        $guestTokenHash = self::guestTokenHash($guestToken);
        $this->transaction(function () use ($userId, $guestTokenHash): void {
            $userCartId = $this->carts->userCartId($userId, true, true);
            if ($userCartId === null) {
                throw new \RuntimeException('The account cart could not be created.');
            }

            $guestCartId = $this->carts->guestCartId($guestTokenHash, false, true);
            if ($guestCartId === null) {
                return;
            }

            foreach ($this->carts->itemsForMerge($guestCartId) as $guestItem) {
                $accountItem = $this->carts->itemIdForOffer(
                    $userCartId,
                    $guestItem['offer_id'],
                    true,
                );
                if ($accountItem === null) {
                    $this->carts->insertItem(
                        $userCartId,
                        $guestItem['offer_id'],
                        $guestItem['quantity'],
                    );
                    continue;
                }

                $quantity = $accountItem['quantity'] + $guestItem['quantity'];
                if ($quantity > self::MAX_QUANTITY) {
                    throw new ApiException(
                        409,
                        'CART_MERGE_CONFLICT',
                        'The guest and account cart quantities exceed the limit for an offer. Reduce the guest cart quantity and try again.',
                    );
                }
                $this->carts->setQuantity($accountItem['id'], $quantity);
            }

            $this->carts->deleteCart($guestCartId);
        });
    }

    private function assertOfferAvailable(int $offerId, bool $lock): void
    {
        $offer = $this->carts->offer($offerId, $lock);
        if ($offer === null) {
            throw new ApiException(404, 'OFFER_NOT_FOUND', 'The selected offer was not found.');
        }
        if (!self::offerIsActive($offer)) {
            throw new ApiException(
                409,
                'OFFER_UNAVAILABLE',
                'This seller offer is no longer available. Refresh the product page and select an active offer.',
            );
        }
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

    private static function multiplyPrice(int $price, int $quantity): int
    {
        if ($price < 0 || $price > intdiv(PHP_INT_MAX, $quantity)) {
            throw new ApiException(
                409,
                'CART_TOTAL_TOO_LARGE',
                'The cart total is outside the supported range.',
            );
        }

        return $price * $quantity;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
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
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function assertFields(array $input, array $allowed): void
    {
        $fields = [];
        foreach (array_diff(array_keys($input), $allowed) as $field) {
            $fields[(string) $field] = 'This field is not supported.';
        }
        if ($fields !== []) {
            throw new ValidationError('One or more fields are invalid.', $fields);
        }
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        if (!is_int($value) || $value < 1) {
            throw new ValidationError('One or more fields are invalid.', [
                $field => 'Must be a positive integer.',
            ]);
        }

        return $value;
    }

    private static function quantity(mixed $value): int
    {
        if (!is_int($value) || $value < 1 || $value > self::MAX_QUANTITY) {
            throw new ValidationError('One or more fields are invalid.', [
                'quantity' => sprintf(
                    'Must be an integer between 1 and %d.',
                    self::MAX_QUANTITY,
                ),
            ]);
        }

        return $value;
    }

    private static function guestTokenHash(string $guestToken): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $guestToken) !== 1) {
            throw new ApiException(
                400,
                'INVALID_GUEST_CART_TOKEN',
                'The guest cart could not be identified. Refresh the page and try again.',
            );
        }

        return hash('sha256', $guestToken);
    }
}
