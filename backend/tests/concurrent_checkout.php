<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Security\DigitalKeyCipher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\ApiException;

require dirname(__DIR__) . '/bootstrap.php';

if (!function_exists('pcntl_fork')) {
    throw new RuntimeException('The concurrent checkout test requires the PHP pcntl extension.');
}

function expectConcurrentCheckout(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$suffix = bin2hex(random_bytes(6));
$keyRing = json_encode(['1' => base64_encode(str_repeat('c', 32))], JSON_THROW_ON_ERROR);
putenv('KEY_ENCRYPTION_KEYS=' . $keyRing);
putenv('KEY_ENCRYPTION_KEY_VERSION=1');

$pdo = Connection::create();
$cipher = DigitalKeyCipher::fromEnvironment();
$userIds = [];
$offerId = null;
$keyId = null;
$barrierPath = sys_get_temp_dir() . '/gamehub-checkout-start-' . $suffix;
$resultPaths = [
    sys_get_temp_dir() . '/gamehub-checkout-result-a-' . $suffix,
    sys_get_temp_dir() . '/gamehub-checkout-result-b-' . $suffix,
];
$childPids = [];

$cleanup = static function () use (
    &$pdo,
    &$userIds,
    &$offerId,
    &$keyId,
    $barrierPath,
    $resultPaths,
): void {
    foreach ([$barrierPath, ...$resultPaths] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    if (!$pdo instanceof PDO) {
        $pdo = Connection::create();
    }
    if ($keyId !== null) {
        $pdo->prepare('DELETE FROM digital_keys WHERE id = :id')->execute(['id' => $keyId]);
    }
    if (count($userIds) === 2) {
        $pdo->prepare(
            'DELETE FROM order_items
             WHERE order_id IN (
                 SELECT id FROM orders WHERE user_id IN (:user_a, :user_b)
             )',
        )->execute(['user_a' => $userIds[0], 'user_b' => $userIds[1]]);
        $pdo->prepare('DELETE FROM orders WHERE user_id IN (:user_a, :user_b)')
            ->execute(['user_a' => $userIds[0], 'user_b' => $userIds[1]]);
        $pdo->prepare('DELETE FROM users WHERE id IN (:user_a, :user_b)')
            ->execute(['user_a' => $userIds[0], 'user_b' => $userIds[1]]);
    }
    if ($offerId !== null) {
        $pdo->prepare('DELETE FROM offers WHERE id = :id')->execute(['id' => $offerId]);
    }
};

try {
    $pdo->beginTransaction();
    $reference = $pdo->query(<<<'SQL'
SELECT product.id AS product_id, seller.id AS seller_id,
       platform.id AS platform_id, region.id AS region_id
FROM products AS product
JOIN offers AS offer ON offer.product_id = product.id
JOIN seller_profiles AS seller ON seller.id = offer.seller_id
JOIN platforms AS platform ON platform.id = offer.platform_id
JOIN regions AS region ON region.id = offer.region_id
WHERE product.status = 'active'
  AND seller.status = 'approved'
  AND platform.status = 'active'
  AND region.status = 'active'
ORDER BY offer.id
LIMIT 1
SQL)->fetch();
    if ($reference === false) {
        throw new RuntimeException('An active catalog offer is required for the concurrency test.');
    }

    $offerInsert = $pdo->prepare(
        "INSERT INTO offers (
            product_id, seller_id, platform_id, region_id,
            price_minor, currency, status
         ) VALUES (
            :product_id, :seller_id, :platform_id, :region_id, 100, 'EUR', 'active'
         )",
    );
    $offerInsert->execute([
        'product_id' => $reference['product_id'],
        'seller_id' => $reference['seller_id'],
        'platform_id' => $reference['platform_id'],
        'region_id' => $reference['region_id'],
    ]);
    $offerId = (int) $pdo->lastInsertId();

    $insertUser = $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name)
         VALUES (:email, :password_hash, :display_name)',
    );
    foreach (['a', 'b'] as $label) {
        $insertUser->execute([
            'email' => "concurrent-{$label}-{$suffix}@example.test",
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'display_name' => 'Concurrent Checkout Test',
        ]);
        $userIds[] = (int) $pdo->lastInsertId();
    }

    $encrypted = $cipher->encrypt('GAMEHUB-DEMO-NOT-REDEEMABLE-CONCURRENT', $offerId);
    $insertKey = $pdo->prepare(
        "INSERT INTO digital_keys (offer_id, ciphertext, nonce, key_version, status)
         VALUES (:offer_id, :ciphertext, :nonce, :key_version, 'available')",
    );
    $insertKey->execute([
        'offer_id' => $offerId,
        'ciphertext' => $encrypted['ciphertext'],
        'nonce' => $encrypted['nonce'],
        'key_version' => $encrypted['keyVersion'],
    ]);
    $keyId = (int) $pdo->lastInsertId();
    $pdo->commit();

    $cartService = new CartService($pdo, new CartRepository($pdo));
    foreach ($userIds as $userId) {
        $cartService->add($userId, ['offerId' => $offerId, 'quantity' => 1]);
    }

    unset($cartService, $offerInsert, $insertUser, $insertKey);
    $pdo = null;

    foreach ($resultPaths as $index => $resultPath) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Could not start concurrent checkout worker.');
        }
        if ($pid === 0) {
            try {
                for ($wait = 0; $wait < 5000 && !is_file($barrierPath); $wait++) {
                    usleep(1000);
                }
                if (!is_file($barrierPath)) {
                    throw new RuntimeException('The checkout test start barrier timed out.');
                }

                $workerPdo = Connection::create();
                $service = new CheckoutService(
                    $workerPdo,
                    new OrderRepository($workerPdo),
                    DigitalKeyCipher::fromEnvironment(),
                );
                $result = $service->checkout(
                    $userIds[$index],
                    'concurrent-' . $suffix . '-' . $index,
                    bin2hex(random_bytes(16)),
                );
                file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX);
                exit(0);
            } catch (ApiException $exception) {
                file_put_contents(
                    $resultPath,
                    json_encode(['errorCode' => $exception->errorCode], JSON_THROW_ON_ERROR),
                    LOCK_EX,
                );
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents(
                    $resultPath,
                    json_encode(['error' => $exception::class], JSON_THROW_ON_ERROR),
                    LOCK_EX,
                );
                exit(1);
            }
        }
        $childPids[] = $pid;
    }

    file_put_contents($barrierPath, 'start', LOCK_EX);
    foreach ($childPids as $pid) {
        pcntl_waitpid($pid, $status);
        expectConcurrentCheckout(
            pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
            'A checkout worker failed unexpectedly.',
        );
    }

    $pdo = Connection::create();
    $results = array_map(
        static function (string $path): array {
            $content = file_get_contents($path);
            if (!is_string($content)) {
                throw new RuntimeException('A concurrent checkout worker returned no result.');
            }
            $result = json_decode($content, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($result)) {
                throw new RuntimeException('A concurrent checkout worker returned invalid JSON.');
            }
            return $result;
        },
        $resultPaths,
    );
    $succeeded = array_values(array_filter(
        $results,
        static fn (array $result): bool => ($result['httpStatus'] ?? null) === 201,
    ));
    $failed = array_values(array_filter(
        $results,
        static fn (array $result): bool => ($result['errorCode'] ?? null) === 'INSUFFICIENT_INVENTORY',
    ));
    expectConcurrentCheckout(
        count($succeeded) === 1 && count($failed) === 1,
        'Exactly one concurrent checkout should consume the single available key.',
    );

    $keyStatus = $pdo->prepare(
        "SELECT status, order_item_id FROM digital_keys WHERE id = :id",
    );
    $keyStatus->execute(['id' => $keyId]);
    $assignedKey = $keyStatus->fetch();
    expectConcurrentCheckout(
        $assignedKey !== false
            && $assignedKey['status'] === 'sold'
            && $assignedKey['order_item_id'] !== null,
        'The one available key should be assigned to exactly one order item.',
    );

    $ordersCount = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id IN (:user_a, :user_b)');
    $ordersCount->execute(['user_a' => $userIds[0], 'user_b' => $userIds[1]]);
    expectConcurrentCheckout(
        (int) $ordersCount->fetchColumn() === 1,
        'Concurrent purchases must not create an order without its assigned key.',
    );

    fwrite(STDOUT, "Concurrent checkout inventory-locking check passed.\n");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($childPids as $pid) {
        $result = pcntl_waitpid($pid, $status, WNOHANG);
        if ($result === 0) {
            pcntl_waitpid($pid, $status);
        }
    }
    $cleanup();
}
