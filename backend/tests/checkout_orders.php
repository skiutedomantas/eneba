<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Security\DigitalKeyCipher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Support\ApiException;
use App\Support\ValidationError;

require dirname(__DIR__) . '/bootstrap.php';

function expectCheckout(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$keyRing = json_encode(['1' => base64_encode(str_repeat('k', 32))], JSON_THROW_ON_ERROR);
putenv('KEY_ENCRYPTION_KEYS=' . $keyRing);
putenv('KEY_ENCRYPTION_KEY_VERSION=1');

$pdo = Connection::create();
$cipher = DigitalKeyCipher::fromEnvironment();
$cart = new CartService($pdo, new CartRepository($pdo));
$orders = new OrderRepository($pdo);
$checkout = new CheckoutService($pdo, $orders, $cipher);
$orderService = new OrderService($pdo, $orders, $cipher);
$suffix = bin2hex(random_bytes(6));
$requestId = bin2hex(random_bytes(16));
$pdo->beginTransaction();

try {
    $userInsert = $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name)
         VALUES (:email, :password_hash, :display_name)',
    );
    $userInsert->execute([
        'email' => "checkout-{$suffix}@example.test",
        'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
        'display_name' => 'Checkout Test',
    ]);
    $userId = (int) $pdo->lastInsertId();

    $otherUserInsert = $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name)
         VALUES (:email, :password_hash, :display_name)',
    );
    $otherUserInsert->execute([
        'email' => "checkout-other-{$suffix}@example.test",
        'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
        'display_name' => 'Other Checkout Test',
    ]);
    $otherUserId = (int) $pdo->lastInsertId();

    $offers = $pdo->query(<<<'SQL'
SELECT offer.id, offer.price_minor, offer.currency
FROM offers AS offer
JOIN products AS product ON product.id = offer.product_id
JOIN seller_profiles AS seller ON seller.id = offer.seller_id
JOIN platforms AS platform ON platform.id = offer.platform_id
JOIN regions AS region ON region.id = offer.region_id
WHERE offer.status = 'active'
  AND product.status = 'active'
  AND seller.status = 'approved'
  AND platform.status = 'active'
  AND region.status = 'active'
  AND NOT EXISTS (
      SELECT 1 FROM digital_keys AS key_row
      WHERE key_row.offer_id = offer.id AND key_row.status = 'available'
  )
ORDER BY offer.id
LIMIT 2
SQL)->fetchAll();
    expectCheckout(count($offers) === 2, 'Two active zero-inventory offers are required for checkout tests.');

    $insertKey = $pdo->prepare(
        "INSERT INTO digital_keys (offer_id, ciphertext, nonce, key_version, status)
         VALUES (:offer_id, :ciphertext, :nonce, :key_version, 'available')",
    );
    $storeFictionalKey = static function (int $offerId, string $plaintext) use ($cipher, $insertKey): void {
        $encrypted = $cipher->encrypt($plaintext, $offerId);
        $insertKey->execute([
            'offer_id' => $offerId,
            'ciphertext' => $encrypted['ciphertext'],
            'nonce' => $encrypted['nonce'],
            'key_version' => $encrypted['keyVersion'],
        ]);
    };

    $purchasableOfferId = (int) $offers[0]['id'];
    $unitPriceMinor = (int) $offers[0]['price_minor'];
    $currency = (string) $offers[0]['currency'];
    $storeFictionalKey($purchasableOfferId, 'GAMEHUB-DEMO-NOT-REDEEMABLE-TEST-0001');
    $storeFictionalKey($purchasableOfferId, 'GAMEHUB-DEMO-NOT-REDEEMABLE-TEST-0002');
    $cart->add($userId, ['offerId' => $purchasableOfferId, 'quantity' => 2]);

    $idempotencyKey = 'checkout-test-' . $suffix;
    $result = $checkout->checkout($userId, $idempotencyKey, $requestId);
    expectCheckout(
        $result['httpStatus'] === 201
            && $result['data']['status'] === 'paid'
            && $result['data']['currency'] === $currency
            && $result['data']['totalMinor'] === $unitPriceMinor * 2,
        'A valid simulated checkout should create and pay an order at current prices.',
    );
    $orderId = $result['data']['orderId'];

    $emptyCart = $cart->get($userId)['data'];
    expectCheckout(
        $emptyCart['items'] === [] && $emptyCart['itemCount'] === 0,
        'A successful checkout should clear the purchased cart.',
    );

    $soldKeys = $pdo->prepare(
        "SELECT COUNT(*) FROM digital_keys
         WHERE status = 'sold'
           AND order_item_id IN (
               SELECT id FROM order_items WHERE order_id = :order_id
           )",
    );
    $soldKeys->execute(['order_id' => $orderId]);
    expectCheckout(
        (int) $soldKeys->fetchColumn() === 2,
        'Every purchased key should be assigned exactly once to the order item.',
    );

    $replayed = $checkout->checkout($userId, $idempotencyKey, $requestId);
    expectCheckout(
        $replayed['httpStatus'] === 200 && $replayed['data']['orderId'] === $orderId,
        'Retrying a completed checkout should return the original order.',
    );

    $order = $orderService->getForUser($userId, $orderId)['data'];
    expectCheckout(
        $order['items'][0]['quantity'] === 2
            && $order['items'][0]['unitPriceMinor'] === $unitPriceMinor,
        'Order details should preserve purchase-time item and price snapshots.',
    );
    $history = $orderService->listForUser($userId, [])['data'];
    expectCheckout(
        count($history) === 1 && $history[0]['id'] === $orderId,
        'The authenticated order history should include the new order.',
    );

    $delivery = $orderService->keysForUser($userId, $orderId, $requestId)['data'];
    expectCheckout(
        count($delivery['items']) === 1
            && count($delivery['items'][0]['keys']) === 2
            && str_starts_with($delivery['items'][0]['keys'][0], 'GAMEHUB-DEMO-NOT-REDEEMABLE-'),
        'Paid-order delivery should decrypt and return fictional keys only to the owner.',
    );
    try {
        $orderService->getForUser($otherUserId, $orderId);
        throw new RuntimeException('An account retrieved another user order.');
    } catch (ApiException $exception) {
        expectCheckout($exception->errorCode === 'ORDER_NOT_FOUND', 'Order ownership should conceal other users’ orders.');
    }
    try {
        $orderService->keysForUser($otherUserId, $orderId, $requestId);
        throw new RuntimeException('An account retrieved another user digital key.');
    } catch (ApiException $exception) {
        expectCheckout($exception->errorCode === 'ORDER_NOT_FOUND', 'Digital-key delivery must enforce order ownership.');
    }

    $pdo->prepare("UPDATE orders SET status = 'failed', paid_at = NULL WHERE id = :id")
        ->execute(['id' => $orderId]);
    try {
        $orderService->keysForUser($userId, $orderId, $requestId);
        throw new RuntimeException('Keys were revealed for an unpaid order.');
    } catch (ApiException $exception) {
        expectCheckout($exception->errorCode === 'ORDER_NOT_PAID', 'Unpaid key access should be rejected.');
    }
    $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = UTC_TIMESTAMP(6) WHERE id = :id")
        ->execute(['id' => $orderId]);

    $insufficientOfferId = (int) $offers[1]['id'];
    $storeFictionalKey($insufficientOfferId, 'GAMEHUB-DEMO-NOT-REDEEMABLE-TEST-ONLY-0001');
    $storeFictionalKey($purchasableOfferId, 'GAMEHUB-DEMO-NOT-REDEEMABLE-TEST-ONLY-0002');
    $originalCurrency = (string) $offers[1]['currency'];
    $pdo->prepare("UPDATE offers SET currency = 'USD' WHERE id = :id")
        ->execute(['id' => $insufficientOfferId]);
    $cart->add($userId, ['offerId' => $purchasableOfferId, 'quantity' => 1]);
    $cart->add($userId, ['offerId' => $insufficientOfferId, 'quantity' => 1]);
    try {
        $checkout->checkout($userId, 'mixed-currency-' . $suffix, $requestId);
        throw new RuntimeException('Checkout accepted a mixed-currency cart.');
    } catch (ApiException $exception) {
        expectCheckout(
            $exception->errorCode === 'MIXED_CURRENCY_CART',
            'Mixed-currency carts should be rejected explicitly.',
        );
    }
    $mixedCurrencyCart = $cart->get($userId)['data'];
    expectCheckout(
        count($mixedCurrencyCart['items']) === 2
            && $mixedCurrencyCart['itemCount'] === 2,
        'A mixed-currency failure must preserve every cart line.',
    );
    foreach ($mixedCurrencyCart['items'] as $item) {
        $cart->remove($userId, $item['id']);
    }
    $pdo->prepare('UPDATE offers SET currency = :currency WHERE id = :id')
        ->execute(['currency' => $originalCurrency, 'id' => $insufficientOfferId]);

    $cart->add($userId, ['offerId' => $purchasableOfferId, 'quantity' => 1]);
    $cart->add($userId, ['offerId' => $insufficientOfferId, 'quantity' => 2]);
    try {
        $checkout->checkout($userId, 'insufficient-test-' . $suffix, $requestId);
        throw new RuntimeException('Checkout succeeded with insufficient key inventory.');
    } catch (ApiException $exception) {
        expectCheckout(
            $exception->errorCode === 'INSUFFICIENT_INVENTORY',
            'Insufficient inventory should produce an explicit conflict.',
        );
    }
    $preservedCart = $cart->get($userId)['data'];
    expectCheckout(
        count($preservedCart['items']) === 2
            && $preservedCart['itemCount'] === 3,
        'A failed checkout must preserve all cart items and quantities.',
    );
    $preservedItems = $preservedCart['items'];
    expectCheckout(
        $preservedItems[0]['offerId'] === $purchasableOfferId
            && $preservedItems[0]['quantity'] === 1
            && $preservedItems[1]['offerId'] === $insufficientOfferId
            && $preservedItems[1]['quantity'] === 2,
        'A failed checkout must preserve all cart items and quantities.',
    );
    $availableKeyCount = $pdo->prepare(
        "SELECT COUNT(*) FROM digital_keys
         WHERE offer_id = :offer_id AND status = 'available'",
    );
    foreach ([$purchasableOfferId, $insufficientOfferId] as $offerId) {
        $availableKeyCount->execute(['offer_id' => $offerId]);
        expectCheckout(
            (int) $availableKeyCount->fetchColumn() === 1,
            'Failed checkout must leave all locked keys available.',
        );
    }

    $corruptKey = $pdo->prepare(
        "SELECT id FROM digital_keys
         WHERE offer_id = :offer_id AND status = 'available'
         ORDER BY id LIMIT 1",
    );
    $corruptKey->execute(['offer_id' => $purchasableOfferId]);
    $corruptKeyId = (int) $corruptKey->fetchColumn();
    $pdo->prepare('UPDATE digital_keys SET ciphertext = :ciphertext WHERE id = :id')
        ->execute(['ciphertext' => 'invalid-ciphertext', 'id' => $corruptKeyId]);
    $invalidKeyRejected = false;
    try {
        $checkout->checkout($userId, 'invalid-key-test-' . $suffix, $requestId);
    } catch (RuntimeException $exception) {
        $invalidKeyRejected = str_contains($exception->getMessage(), 'authenticated');
    }
    expectCheckout(
        $invalidKeyRejected
            && $cart->get($userId)['data']['itemCount'] === 3,
        'Checkout must fail closed on corrupt encrypted keys and preserve the cart.',
    );

    try {
        $checkout->checkout($userId, 'bad key', $requestId);
        throw new RuntimeException('An invalid idempotency key was accepted.');
    } catch (ValidationError $exception) {
        expectCheckout(
            isset($exception->fields['Idempotency-Key']),
            'Invalid idempotency keys should identify the request header.',
        );
    }

    $encrypted = $cipher->encrypt('GAMEHUB-DEMO-NOT-REDEEMABLE-TAMPER', $purchasableOfferId);
    $wrongOfferRejected = false;
    try {
        $cipher->decrypt(
            $encrypted['ciphertext'],
            $encrypted['nonce'],
            $encrypted['keyVersion'],
            $purchasableOfferId + 1,
        );
    } catch (RuntimeException $exception) {
        $wrongOfferRejected = str_contains($exception->getMessage(), 'offer');
    }
    expectCheckout($wrongOfferRejected, 'Decryption should bind a key payload to its offer.');

    fwrite(STDOUT, "Checkout, order history, and fictional key delivery checks passed.\n");
} finally {
    $pdo->rollBack();
}
