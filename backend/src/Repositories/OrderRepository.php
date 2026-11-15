<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function orderIdForIdempotency(
        int $userId,
        string $idempotencyKey,
        bool $lock = false,
    ): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM orders
             WHERE user_id = :user_id AND idempotency_key = :idempotency_key
             LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute([
            'user_id' => $userId,
            'idempotency_key' => $idempotencyKey,
        ]);
        $orderId = $statement->fetchColumn();

        return $orderId === false ? null : (int) $orderId;
    }

    public function lockCartId(int $userId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM carts WHERE user_id = :user_id LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['user_id' => $userId]);
        $cartId = $statement->fetchColumn();

        return $cartId === false ? null : (int) $cartId;
    }

    /**
     * @return list<array{id: int, offer_id: int, quantity: int}>
     */
    public function cartItemsForUpdate(int $cartId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, offer_id, quantity
             FROM cart_items
             WHERE cart_id = :cart_id
             ORDER BY offer_id, id
             FOR UPDATE',
        );
        $statement->execute(['cart_id' => $cartId]);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'offer_id' => (int) $row['offer_id'],
                'quantity' => (int) $row['quantity'],
            ],
            $statement->fetchAll(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function offerForCheckout(int $offerId, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    offer.id,
    offer.status AS offer_status,
    offer.price_minor,
    offer.currency,
    product.name AS product_name,
    product.status AS product_status,
    seller.display_name AS seller_name,
    seller.status AS seller_status,
    platform.name AS platform_name,
    platform.status AS platform_status,
    region.name AS region_name,
    region.status AS region_status
FROM offers AS offer
JOIN products AS product ON product.id = offer.product_id
JOIN seller_profiles AS seller ON seller.id = offer.seller_id
JOIN platforms AS platform ON platform.id = offer.platform_id
JOIN regions AS region ON region.id = offer.region_id
WHERE offer.id = :offer_id
LIMIT 1
SQL . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['offer_id' => $offerId]);
        $offer = $statement->fetch();

        return $offer === false ? null : $offer;
    }

    /**
     * @return list<array{id: int, ciphertext: string, nonce: string, key_version: int}>
     */
    public function availableKeysForUpdate(int $offerId, int $quantity): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, ciphertext, nonce, key_version
             FROM digital_keys
             WHERE offer_id = :offer_id AND status = \'available\'
             ORDER BY id
             LIMIT :quantity
             FOR UPDATE',
        );
        $statement->bindValue(':offer_id', $offerId, PDO::PARAM_INT);
        $statement->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'ciphertext' => (string) $row['ciphertext'],
                'nonce' => (string) $row['nonce'],
                'key_version' => (int) $row['key_version'],
            ],
            $statement->fetchAll(),
        );
    }

    public function createPaidOrder(
        int $userId,
        string $currency,
        int $totalMinor,
        string $idempotencyKey,
    ): int {
        $statement = $this->pdo->prepare(
            "INSERT INTO orders (
                user_id, status, currency, subtotal_minor, total_minor,
                idempotency_key, paid_at
             ) VALUES (
                :user_id, 'paid', :currency, :subtotal_minor, :total_minor,
                :idempotency_key, UTC_TIMESTAMP(6)
             )",
        );
        $statement->execute([
            'user_id' => $userId,
            'currency' => $currency,
            'subtotal_minor' => $totalMinor,
            'total_minor' => $totalMinor,
            'idempotency_key' => $idempotencyKey,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createOrderItem(
        int $orderId,
        int $offerId,
        string $productName,
        string $sellerName,
        string $platformName,
        string $regionName,
        int $quantity,
        int $unitPriceMinor,
        string $currency,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO order_items (
                order_id, offer_id, product_name_snapshot, seller_name_snapshot,
                platform_name_snapshot, region_name_snapshot, quantity,
                unit_price_minor, currency
             ) VALUES (
                :order_id, :offer_id, :product_name, :seller_name, :platform_name,
                :region_name, :quantity, :unit_price_minor, :currency
             )',
        );
        $statement->execute([
            'order_id' => $orderId,
            'offer_id' => $offerId,
            'product_name' => $productName,
            'seller_name' => $sellerName,
            'platform_name' => $platformName,
            'region_name' => $regionName,
            'quantity' => $quantity,
            'unit_price_minor' => $unitPriceMinor,
            'currency' => $currency,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<int> $keyIds
     */
    public function assignKeys(array $keyIds, int $orderItemId): void
    {
        if ($keyIds === []) {
            return;
        }

        $placeholders = [];
        $parameters = ['order_item_id' => $orderItemId];
        foreach ($keyIds as $index => $keyId) {
            $name = 'key_id_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $keyId;
        }

        $statement = $this->pdo->prepare(
            'UPDATE digital_keys
             SET status = \'sold\', order_item_id = :order_item_id
             WHERE status = \'available\' AND id IN (' . implode(', ', $placeholders) . ')',
        );
        $statement->execute($parameters);
        if ($statement->rowCount() !== count($keyIds)) {
            throw new \RuntimeException('The locked digital-key inventory changed unexpectedly.');
        }
    }

    public function clearCart(int $cartId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
        $statement->execute(['cart_id' => $cartId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function orderForUser(int $userId, int $orderId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, status, currency, subtotal_minor, total_minor, created_at, paid_at
             FROM orders
             WHERE id = :order_id AND user_id = :user_id
             LIMIT 1',
        );
        $statement->execute(['order_id' => $orderId, 'user_id' => $userId]);
        $order = $statement->fetch();

        return $order === false ? null : $order;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function orderItems(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, offer_id, product_name_snapshot, seller_name_snapshot,
                    platform_name_snapshot, region_name_snapshot, quantity,
                    unit_price_minor, currency
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY id',
        );
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll();
    }

    /**
     * @return array{total: int, orders: list<array<string, mixed>>}
     */
    public function ordersForUser(int $userId, int $limit, int $offset): array
    {
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
        $count->execute(['user_id' => $userId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT order_row.id, order_row.status, order_row.currency,
                    order_row.subtotal_minor, order_row.total_minor,
                    order_row.created_at, order_row.paid_at,
                    COUNT(item.id) AS item_count
             FROM orders AS order_row
             LEFT JOIN order_items AS item ON item.order_id = order_row.id
             WHERE order_row.user_id = :user_id
             GROUP BY order_row.id
             ORDER BY order_row.created_at DESC, order_row.id DESC
             LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['total' => $total, 'orders' => $statement->fetchAll()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function keysForOrder(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT item.id AS order_item_id, item.offer_id, item.product_name_snapshot,
                    item.quantity, key_row.id AS key_id, key_row.ciphertext,
                    key_row.nonce, key_row.key_version
             FROM order_items AS item
             JOIN digital_keys AS key_row
               ON key_row.order_item_id = item.id AND key_row.status = \'sold\'
             WHERE item.order_id = :order_id
             ORDER BY item.id, key_row.id',
        );
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll();
    }

    public function recordKeyAccess(int $userId, int $orderItemId, string $requestId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO key_access_logs (user_id, order_item_id, request_id)
             VALUES (:user_id, :order_item_id, :request_id)',
        );
        $statement->execute([
            'user_id' => $userId,
            'order_item_id' => $orderItemId,
            'request_id' => $requestId,
        ]);
    }
}
