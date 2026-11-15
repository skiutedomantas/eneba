<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\AuthRepository;
use App\Repositories\CartRepository;
use App\Services\AuthService;
use App\Services\CartService;
use App\Support\ApiException;
use App\Support\ValidationError;

require dirname(__DIR__) . '/bootstrap.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Connection::create();
$cart = new CartService($pdo, new CartRepository($pdo));
$auth = new AuthService($pdo, new AuthRepository($pdo), $cart);
$suffix = bin2hex(random_bytes(6));
$email = "phase4-{$suffix}@example.test";
$otherEmail = "phase4-other-{$suffix}@example.test";
$ip = '203.0.113.41';
$pdo->beginTransaction();

try {
    expect(
        $cart->getGuest(null)['data']['items'] === [],
        'An anonymous visitor without a guest cookie should have an empty cart.',
    );

    $registration = $auth->register([
        'email' => $email,
        'password' => 'marketplace-test-password',
        'displayName' => 'Phase Four Test',
    ], $ip, null);
    expect(
        preg_match('/^[a-f0-9]{64}$/D', $registration['sessionToken']) === 1,
        'Registration should issue an opaque random session token.',
    );
    $registeredSession = $auth->session($registration['sessionToken']);
    expect(
        $registeredSession !== null
            && $auth->presentSessionUser($registeredSession)['id'] === $registration['user']['id'],
        'The session should resolve to the account ID, not the session row ID.',
    );
    try {
        $auth->register([
            'email' => $email,
            'password' => 'marketplace-test-password',
            'displayName' => 'Duplicate Test',
        ], '203.0.113.44', null);
        throw new RuntimeException('A duplicate account email was accepted.');
    } catch (ApiException $exception) {
        expect($exception->errorCode === 'EMAIL_ALREADY_REGISTERED', 'Unexpected duplicate-email error.');
    }

    $expireSession = $pdo->prepare(
        'UPDATE sessions
         SET expires_at = UTC_TIMESTAMP(6) - INTERVAL 1 SECOND
         WHERE token_hash = :token_hash',
    );
    $expireSession->execute([
        'token_hash' => hash('sha256', $registration['sessionToken']),
    ]);
    expect(
        $auth->session($registration['sessionToken']) === null,
        'Expired sessions must not resolve to an authenticated user.',
    );

    $auth->assertCsrf($registration['csrfToken'], $registration['csrfToken'], null);
    try {
        $auth->assertCsrf('invalid-token', $registration['csrfToken'], null);
        throw new RuntimeException('An invalid CSRF token was accepted.');
    } catch (ApiException $exception) {
        expect($exception->errorCode === 'CSRF_VALIDATION_FAILED', 'Unexpected CSRF error.');
    }

    $login = $auth->login([
        'email' => $email,
        'password' => 'marketplace-test-password',
    ], $ip, $registration['sessionToken']);
    expect(
        $auth->session($registration['sessionToken']) === null
            && $auth->session($login['sessionToken']) !== null,
        'Login should revoke the prior session and issue a new one.',
    );

    $offer = $pdo->query(<<<'SQL'
SELECT offer.id, offer.product_id, offer.price_minor
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
      SELECT 1
      FROM digital_keys AS key_row
      WHERE key_row.offer_id = offer.id AND key_row.status = 'available'
  )
ORDER BY offer.id
LIMIT 1
SQL)->fetch();
    expect($offer !== false, 'A zero-inventory active offer should exist for cart testing.');
    $offerId = (int) $offer['id'];
    $initialPrice = (int) $offer['price_minor'];

    $firstCart = $cart->add($registration['user']['id'], [
        'offerId' => $offerId,
        'quantity' => 2,
    ]);
    expect(
        $firstCart['data']['items'][0]['offerId'] === $offerId
            && $firstCart['data']['items'][0]['quantity'] === 2
            && $firstCart['data']['items'][0]['unitPriceMinor'] === $initialPrice
            && $firstCart['data']['items'][0]['lineTotalMinor'] === $initialPrice * 2,
        'An active offer with no keys should be addable at its current server price.',
    );
    $itemId = $firstCart['data']['items'][0]['id'];

    $incrementedCart = $cart->add($registration['user']['id'], [
        'offerId' => $offerId,
        'quantity' => 1,
    ]);
    expect(
        $incrementedCart['data']['items'][0]['quantity'] === 3,
        'Adding an existing offer should increment its quantity.',
    );

    $pdo->prepare('UPDATE offers SET price_minor = price_minor + 17 WHERE id = :id')
        ->execute(['id' => $offerId]);
    $repricedCart = $cart->get($registration['user']['id']);
    expect(
        $repricedCart['data']['items'][0]['unitPriceMinor'] === $initialPrice + 17
            && $repricedCart['data']['items'][0]['lineTotalMinor'] === ($initialPrice + 17) * 3,
        'Cart reads should recalculate totals from the current database offer price.',
    );

    $updatedCart = $cart->update($registration['user']['id'], $itemId, ['quantity' => 4]);
    expect(
        $updatedCart['data']['items'][0]['quantity'] === 4,
        'Updating an active zero-inventory offer should succeed.',
    );

    try {
        $cart->update($registration['user']['id'], $itemId, ['quantity' => 100]);
        throw new RuntimeException('An out-of-range quantity was accepted.');
    } catch (ValidationError $exception) {
        expect(isset($exception->fields['quantity']), 'Invalid quantity should be identified.');
    }

    $otherAccount = $auth->register([
        'email' => $otherEmail,
        'password' => 'marketplace-test-password',
        'displayName' => 'Another Test',
    ], '203.0.113.42', null);
    try {
        $cart->remove($otherAccount['user']['id'], $itemId);
        throw new RuntimeException('A user was allowed to remove another account cart item.');
    } catch (ApiException $exception) {
        expect($exception->errorCode === 'CART_ITEM_NOT_FOUND', 'Cart ownership was not enforced.');
    }

    $coverageOffers = $pdo->query(<<<'SQL'
SELECT product.id AS product_id, MIN(offer.id) AS offer_id
FROM products AS product
JOIN offers AS offer ON offer.product_id = product.id
JOIN seller_profiles AS seller ON seller.id = offer.seller_id
JOIN platforms AS platform ON platform.id = offer.platform_id
JOIN regions AS region ON region.id = offer.region_id
WHERE product.status = 'active'
  AND offer.status = 'active'
  AND seller.status = 'approved'
  AND platform.status = 'active'
  AND region.status = 'active'
  AND NOT EXISTS (
      SELECT 1
      FROM digital_keys AS key_row
      WHERE key_row.offer_id = offer.id AND key_row.status = 'available'
  )
GROUP BY product.id
ORDER BY product.id
SQL)->fetchAll();
    expect(count($coverageOffers) === 30, 'Every active game should have a zero-inventory offer.');
$otherCart = $cart->add($otherAccount['user']['id'], [
    'offerId' => $offerId,
    'quantity' => 2,
]);
$guestToken = bin2hex(random_bytes(32));
    $coverageCart = null;
    foreach ($coverageOffers as $coverageOffer) {
    $coverageCart = $cart->addGuest($guestToken, [
        'offerId' => (int) $coverageOffer['product_id'] === (int) $offer['product_id']
            ? $offerId
            : (int) $coverageOffer['offer_id'],
            'quantity' => 1,
        ]);
    }
    expect(
        $coverageCart !== null
            && count($coverageCart['data']['items']) === 30
        && $coverageCart['data']['itemCount'] === 30
            && count(array_filter(
                $coverageCart['data']['items'],
                static fn (array $item): bool => $item['offerAvailable'],
            )) === 30,
    'An anonymous visitor should be able to add all 30 active games without key inventory.',
    );
expect(
    $otherCart['data']['items'][0]['quantity'] === 2,
    'The account cart should keep its pre-login selection.',
);

$mergedLogin = $auth->login([
    'email' => $otherEmail,
    'password' => 'marketplace-test-password',
], '203.0.113.45', $otherAccount['sessionToken'], $guestToken);
$mergedCart = $cart->get($otherAccount['user']['id']);
expect(
    $auth->session($otherAccount['sessionToken']) === null
        && $auth->session($mergedLogin['sessionToken']) !== null
        && count($mergedCart['data']['items']) === 30
        && $mergedCart['data']['itemCount'] === 32
        && $cart->getGuest($guestToken)['data']['items'] === [],
    'Sign-in should merge the guest cart into the account cart and consume the guest cart.',
);
$mergedOffer = array_values(array_filter(
    $mergedCart['data']['items'],
    static fn (array $item): bool => $item['offerId'] === $offerId,
));
expect(
    count($mergedOffer) === 1 && $mergedOffer[0]['quantity'] === 3,
    'Matching guest and account offer quantities should be combined.',
);

$conflictGuestToken = bin2hex(random_bytes(32));
$cart->addGuest($conflictGuestToken, ['offerId' => $offerId, 'quantity' => 99]);
try {
    $auth->login([
        'email' => $otherEmail,
        'password' => 'marketplace-test-password',
    ], '203.0.113.46', $mergedLogin['sessionToken'], $conflictGuestToken);
    throw new RuntimeException('An over-limit guest cart merge was accepted.');
} catch (ApiException $exception) {
    expect($exception->errorCode === 'CART_MERGE_CONFLICT', 'Unexpected cart merge conflict.');
}
expect(
    $cart->getGuest($conflictGuestToken)['data']['items'][0]['quantity'] === 99,
    'A failed merge should preserve the guest cart contents.',
);

    $pdo->prepare("UPDATE offers SET status = 'inactive' WHERE id = :id")
        ->execute(['id' => $offerId]);
    $unavailableCart = $cart->get($registration['user']['id']);
    expect(
        $unavailableCart['data']['items'][0]['offerAvailable'] === false
            && $unavailableCart['data']['items'][0]['unitPriceMinor'] === null
            && $unavailableCart['data']['totals'] === [],
        'Inactive offers should remain visible without a chargeable price.',
    );
    try {
        $cart->update($registration['user']['id'], $itemId, ['quantity' => 2]);
        throw new RuntimeException('An inactive offer was allowed to change.');
    } catch (ApiException $exception) {
        expect($exception->errorCode === 'OFFER_UNAVAILABLE', 'Unexpected inactive-offer error.');
    }
    try {
        $cart->add($registration['user']['id'], ['offerId' => $offerId, 'quantity' => 1]);
        throw new RuntimeException('An inactive offer was allowed to be added.');
    } catch (ApiException $exception) {
        expect($exception->errorCode === 'OFFER_UNAVAILABLE', 'Inactive offer add was not rejected.');
    }
    expect(
        $cart->remove($registration['user']['id'], $itemId)['data']['items'] === [],
        'Unavailable cart items should remain removable.',
    );

    $auth->logout($login['sessionToken']);
    expect(
        $auth->session($login['sessionToken']) === null,
        'Logout should revoke the current session.',
    );

    for ($attempt = 0; $attempt < 10; $attempt++) {
        try {
            $auth->login([
                'email' => "missing-{$suffix}@example.test",
                'password' => 'marketplace-test-password',
            ], '203.0.113.43', null);
            throw new RuntimeException('Invalid credentials were accepted.');
        } catch (ApiException $exception) {
            expect($exception->errorCode === 'INVALID_CREDENTIALS', 'Unexpected login failure.');
        }
    }
    try {
        $auth->login([
            'email' => "missing-{$suffix}@example.test",
            'password' => 'marketplace-test-password',
        ], '203.0.113.43', null);
        throw new RuntimeException('The login rate limit was not enforced.');
    } catch (ApiException $exception) {
        expect($exception->errorCode === 'RATE_LIMITED', 'Unexpected rate-limit response.');
    }

    fwrite(STDOUT, "Authentication and cart service checks passed.\n");
} finally {
    $pdo->rollBack();
}
