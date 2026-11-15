<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Security\DigitalKeyCipher;
use App\Support\ApiException;
use App\Support\QueryParameters;
use PDO;
use Throwable;

final class OrderService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly OrderRepository $orders,
        private readonly ?DigitalKeyCipher $cipher = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int}}
     */
    public function listForUser(int $userId, array $query): array
    {
        $page = QueryParameters::integer($query, 'page', 1, 1, 10000);
        $perPage = QueryParameters::integer($query, 'perPage', 20, 1, 50);
        $result = $this->orders->ordersForUser(
            $userId,
            $perPage,
            ($page - 1) * $perPage,
        );

        $orders = array_map(
            static fn (array $order): array => self::presentListOrder($order),
            $result['orders'],
        );

        return [
            'data' => $orders,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $result['total'],
            ],
        ];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function getForUser(int $userId, int $orderId): array
    {
        $order = $this->orders->orderForUser($userId, $orderId);
        if ($order === null) {
            throw new ApiException(404, 'ORDER_NOT_FOUND', 'The requested order was not found.');
        }

        return ['data' => self::presentOrder($order, $this->orders->orderItems($orderId))];
    }

    /**
     * @return array{data: array{orderId: int, items: list<array{orderItemId: int, productName: string, keys: list<string>}>}}
     */
    public function keysForUser(int $userId, int $orderId, string $requestId): array
    {
        $result = $this->transaction(function () use ($userId, $orderId, $requestId): array {
            $order = $this->orders->orderForUser($userId, $orderId);
            if ($order === null) {
                throw new ApiException(404, 'ORDER_NOT_FOUND', 'The requested order was not found.');
            }
            if ($order['status'] !== 'paid') {
                throw new ApiException(409, 'ORDER_NOT_PAID', 'Keys are available only for paid orders.');
            }

            $rows = $this->orders->keysForOrder($orderId);
            $items = [];
            $quantities = [];
            foreach ($this->orders->orderItems($orderId) as $orderItem) {
                $orderItemId = (int) $orderItem['id'];
                $items[$orderItemId] = [
                    'orderItemId' => $orderItemId,
                    'productName' => (string) $orderItem['product_name_snapshot'],
                    'keys' => [],
                ];
                $quantities[$orderItemId] = (int) $orderItem['quantity'];
            }

            foreach ($rows as $row) {
                $orderItemId = (int) $row['order_item_id'];
                if (!isset($items[$orderItemId])) {
                    throw new \RuntimeException('A delivered key does not belong to an order item.');
                }
                if ($this->cipher === null) {
                    throw new \RuntimeException('Digital key encryption is not configured.');
                }
                $items[$orderItemId]['keys'][] = $this->cipher->decrypt(
                    (string) $row['ciphertext'],
                    (string) $row['nonce'],
                    (int) $row['key_version'],
                    (int) $row['offer_id'],
                );
            }

            foreach ($items as $orderItemId => $item) {
                if (count($item['keys']) !== $quantities[$orderItemId]) {
                    throw new \RuntimeException('Paid order key inventory is incomplete.');
                }
                $this->orders->recordKeyAccess($userId, $orderItemId, $requestId);
            }

            return [
                'data' => [
                    'orderId' => $orderId,
                    'items' => array_values($items),
                ],
            ];
        });

        error_log(json_encode([
            'event' => 'order.keys_revealed',
            'requestId' => $requestId,
            'orderId' => $orderId,
            'itemCount' => count($result['data']['items']),
        ], JSON_UNESCAPED_SLASHES));
        return $result;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private static function presentListOrder(array $order): array
    {
        return [
            'id' => (int) $order['id'],
            'status' => (string) $order['status'],
            'currency' => (string) $order['currency'],
            'subtotalMinor' => (int) $order['subtotal_minor'],
            'totalMinor' => (int) $order['total_minor'],
            'itemCount' => (int) $order['item_count'],
            'createdAt' => (string) $order['created_at'],
            'paidAt' => $order['paid_at'] === null ? null : (string) $order['paid_at'],
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function presentOrder(array $order, array $items): array
    {
        return [
            'id' => (int) $order['id'],
            'status' => (string) $order['status'],
            'currency' => (string) $order['currency'],
            'subtotalMinor' => (int) $order['subtotal_minor'],
            'totalMinor' => (int) $order['total_minor'],
            'createdAt' => (string) $order['created_at'],
            'paidAt' => $order['paid_at'] === null ? null : (string) $order['paid_at'],
            'items' => array_map(
                static fn (array $item): array => [
                    'id' => (int) $item['id'],
                    'offerId' => (int) $item['offer_id'],
                    'productName' => (string) $item['product_name_snapshot'],
                    'sellerName' => (string) $item['seller_name_snapshot'],
                    'platformName' => (string) $item['platform_name_snapshot'],
                    'regionName' => (string) $item['region_name_snapshot'],
                    'quantity' => (int) $item['quantity'],
                    'unitPriceMinor' => (int) $item['unit_price_minor'],
                    'currency' => (string) $item['currency'],
                ],
                $items,
            ),
        ];
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
}
