<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CartRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function userCartId(int $userId, bool $create = false, bool $lock = false): ?int
    {
        if ($create) {
            $insert = $this->pdo->prepare(
                'INSERT INTO carts (user_id)
                 VALUES (:user_id)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
            );
            $insert->execute(['user_id' => $userId]);
        }

        $statement = $this->pdo->prepare(
            'SELECT id FROM carts WHERE user_id = :user_id LIMIT 1'
                . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['user_id' => $userId]);
        $cartId = $statement->fetchColumn();

        return $cartId === false ? null : (int) $cartId;
    }

    public function guestCartId(
        string $guestTokenHash,
        bool $create = false,
        bool $lock = false,
    ): ?int {
        if ($create) {
            $insert = $this->pdo->prepare(
                'INSERT INTO carts (guest_token_hash)
                 VALUES (:guest_token_hash)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
            );
            $insert->execute(['guest_token_hash' => $guestTokenHash]);
        }

        $statement = $this->pdo->prepare(
            'SELECT id FROM carts WHERE guest_token_hash = :guest_token_hash LIMIT 1'
                . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['guest_token_hash' => $guestTokenHash]);
        $cartId = $statement->fetchColumn();

        return $cartId === false ? null : (int) $cartId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function offer(int $offerId, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    offer.id,
    offer.status AS offer_status,
    offer.price_minor,
    offer.currency,
    product.id AS product_id,
    product.slug AS product_slug,
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
     * @return list<array<string, mixed>>
     */
    public function items(int $cartId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    item.id,
    item.offer_id,
    item.quantity,
    offer.status AS offer_status,
    offer.price_minor,
    offer.currency,
    product.slug AS product_slug,
    product.name AS product_name,
    product.status AS product_status,
    seller.display_name AS seller_name,
    seller.status AS seller_status,
    platform.name AS platform_name,
    platform.status AS platform_status,
    region.name AS region_name,
    region.status AS region_status
FROM cart_items AS item
JOIN offers AS offer ON offer.id = item.offer_id
JOIN products AS product ON product.id = offer.product_id
JOIN seller_profiles AS seller ON seller.id = offer.seller_id
JOIN platforms AS platform ON platform.id = offer.platform_id
JOIN regions AS region ON region.id = offer.region_id
WHERE item.cart_id = :cart_id
ORDER BY item.created_at ASC, item.id ASC
SQL);
        $statement->execute(['cart_id' => $cartId]);

        return $statement->fetchAll();
    }

    /**
     * @return list<array{id: int, offer_id: int, quantity: int}>
     */
    public function itemsForMerge(int $cartId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, offer_id, quantity
             FROM cart_items
             WHERE cart_id = :cart_id
             ORDER BY id
             FOR UPDATE',
        );
        $statement->execute(['cart_id' => $cartId]);

        return array_map(
            static fn (array $item): array => [
                'id' => (int) $item['id'],
                'offer_id' => (int) $item['offer_id'],
                'quantity' => (int) $item['quantity'],
            ],
            $statement->fetchAll(),
        );
    }

    public function itemIdForOffer(int $cartId, int $offerId, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, quantity
             FROM cart_items
             WHERE cart_id = :cart_id AND offer_id = :offer_id
             LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute([
            'cart_id' => $cartId,
            'offer_id' => $offerId,
        ]);
        $item = $statement->fetch();

        return $item === false
            ? null
            : ['id' => (int) $item['id'], 'quantity' => (int) $item['quantity']];
    }

    public function item(int $cartId, int $itemId, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, offer_id
             FROM cart_items
             WHERE cart_id = :cart_id AND id = :item_id
             LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute([
            'cart_id' => $cartId,
            'item_id' => $itemId,
        ]);
        $item = $statement->fetch();

        return $item === false
            ? null
            : ['id' => (int) $item['id'], 'offer_id' => (int) $item['offer_id']];
    }

    public function insertItem(int $cartId, int $offerId, int $quantity): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO cart_items (cart_id, offer_id, quantity)
             VALUES (:cart_id, :offer_id, :quantity)',
        );
        $statement->execute([
            'cart_id' => $cartId,
            'offer_id' => $offerId,
            'quantity' => $quantity,
        ]);
    }

    public function setQuantity(int $itemId, int $quantity): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE cart_items SET quantity = :quantity WHERE id = :item_id',
        );
        $statement->execute([
            'quantity' => $quantity,
            'item_id' => $itemId,
        ]);
    }

    public function deleteItem(int $itemId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM cart_items WHERE id = :item_id');
        $statement->execute(['item_id' => $itemId]);
    }

    public function deleteCart(int $cartId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM carts WHERE id = :cart_id');
        $statement->execute(['cart_id' => $cartId]);
    }
}
