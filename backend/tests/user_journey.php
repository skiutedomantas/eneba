<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\AuthRepository;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Security\DigitalKeyCipher;
use App\Services\AuthService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;

require dirname(__DIR__) . '/bootstrap.php';

function expectUserJourney(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$keyRing = json_encode((object) [17 => base64_encode(str_repeat('j', 32))], JSON_THROW_ON_ERROR);
putenv('KEY_ENCRYPTION_KEYS=' . $keyRing);
putenv('KEY_ENCRYPTION_KEY_VERSION=17');

$pdo = Connection::create();
$pdo->beginTransaction();

try {
    $cipher = DigitalKeyCipher::fromEnvironment();
    $cartRepository = new CartRepository($pdo);
    $cart = new CartService($pdo, $cartRepository);
    $auth = new AuthService($pdo, new AuthRepository($pdo), $cart);
    $orders = new OrderRepository($pdo);
    $requestId = bin2hex(random_bytes(16));
    $suffix = bin2hex(random_bytes(8));

    $reference = $pdo->query(<<<'SQL'
SELECT product.id AS product_id, offer.seller_id, offer.platform_id, offer.region_id,
       offer.price_minor, offer.currency
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
ORDER BY offer.id
LIMIT 1
SQL)->fetch();
    if ($reference === false) {
        throw new RuntimeException('An active offer is required for the user journey test.');
    }

    $offerInsert = $pdo->prepare(
        'INSERT INTO offers (
            product_id, seller_id, platform_id, region_id, price_minor, currency, status
         ) VALUES (
            :product_id, :seller_id, :platform_id, :region_id, :price_minor, :currency, \'active\'
         )',
    );
    $offerInsert->execute([
        'product_id' => $reference['product_id'],
        'seller_id' => $reference['seller_id'],
        'platform_id' => $reference['platform_id'],
        'region_id' => $reference['region_id'],
        'price_minor' => $reference['price_minor'],
        'currency' => $reference['currency'],
    ]);
    $offerId = (int) $pdo->lastInsertId();

    $encryptedKey = $cipher->encrypt(
        'GAMEHUB-DEMO-NOT-REDEEMABLE-JOURNEY-' . $suffix,
        $offerId,
    );
    $keyInsert = $pdo->prepare(
        "INSERT INTO digital_keys (offer_id, ciphertext, nonce, key_version, status)
         VALUES (:offer_id, :ciphertext, :nonce, :key_version, 'available')",
    );
    $keyInsert->execute([
        'offer_id' => $offerId,
        'ciphertext' => $encryptedKey['ciphertext'],
        'nonce' => $encryptedKey['nonce'],
        'key_version' => $encryptedKey['keyVersion'],
    ]);

    $registration = $auth->register([
        'email' => 'user-journey-' . $suffix . '@example.test',
        'password' => 'phase-six-journey-password',
        'displayName' => 'Journey Test',
    ], '198.51.100.93', null);
    $session = $auth->session($registration['sessionToken']);
    expectUserJourney(
        $session !== null
            && $auth->presentSessionUser($session)['id'] === $registration['user']['id'],
        'Registration should establish an authenticated account session.',
    );
    $auth->assertCsrf($registration['csrfToken'], $registration['csrfToken'], $session);

    $addedCart = $cart->add($registration['user']['id'], [
        'offerId' => $offerId,
        'quantity' => 1,
    ]);
    expectUserJourney(
        $addedCart['data']['itemCount'] === 1
            && $addedCart['data']['items'][0]['offerId'] === $offerId,
        'The registered account should be able to add its selected offer to the cart.',
    );

    $checkout = (new CheckoutService($pdo, $orders, $cipher))->checkout(
        $registration['user']['id'],
        'journey-' . $suffix,
        $requestId,
    );
    expectUserJourney(
        $checkout['httpStatus'] === 201
            && $checkout['data']['status'] === 'paid'
            && $cart->get($registration['user']['id'])['data']['itemCount'] === 0,
        'Checkout should create a paid simulated order and clear the account cart.',
    );

    $orderService = new OrderService($pdo, $orders, $cipher);
    $history = $orderService->listForUser($registration['user']['id'], [])['data'];
    expectUserJourney(
        count($history) === 1 && $history[0]['id'] === $checkout['data']['orderId'],
        'The completed order should appear in the customer order history.',
    );
    $delivery = $orderService->keysForUser(
        $registration['user']['id'],
        $checkout['data']['orderId'],
        $requestId,
    )['data'];
    expectUserJourney(
        count($delivery['items']) === 1
            && count($delivery['items'][0]['keys']) === 1
            && $delivery['items'][0]['keys'][0] === 'GAMEHUB-DEMO-NOT-REDEEMABLE-JOURNEY-' . $suffix,
        'The owner should receive the selected fictional key from the paid order.',
    );

    $retry = (new CheckoutService($pdo, $orders, $cipher))->checkout(
        $registration['user']['id'],
        'journey-' . $suffix,
        $requestId,
    );
    expectUserJourney(
        $retry['httpStatus'] === 200
            && $retry['data']['orderId'] === $checkout['data']['orderId'],
        'Repeating checkout should return the original order.',
    );

    $auth->logout($registration['sessionToken']);
    expectUserJourney(
        $auth->session($registration['sessionToken']) === null,
        'The test account session should be revocable after key delivery.',
    );
    echo "Registration-to-simulated-key-delivery user journey passed; test writes will be rolled back.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
