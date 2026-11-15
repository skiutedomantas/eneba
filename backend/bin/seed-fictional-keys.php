<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\SqlDialect;
use App\Security\DigitalKeyCipher;

require dirname(__DIR__) . '/bootstrap.php';

$environment = getenv('APP_ENV');
if (!in_array($environment, ['local', 'development', 'test'], true)) {
    throw new RuntimeException(
        'Fictional digital keys may only be seeded when APP_ENV is local, development, or test.',
    );
}

$pdo = Connection::create();
$cipher = DigitalKeyCipher::fromEnvironment();
$targetAvailableKeysPerOffer = 2;
$created = 0;

$pdo->beginTransaction();
try {
    $offers = $pdo->query(<<<'SQL'
SELECT offer.id
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
SQL)->fetchAll(PDO::FETCH_COLUMN);

    $availableCount = $pdo->prepare(
        "SELECT COUNT(*) FROM digital_keys WHERE offer_id = :offer_id AND status = 'available'",
    );
    $totalCount = $pdo->prepare('SELECT COUNT(*) FROM digital_keys WHERE offer_id = :offer_id');
    $insert = $pdo->prepare(
        "INSERT INTO digital_keys (offer_id, ciphertext, nonce, key_version, status)
         VALUES (
            :offer_id,
            " . SqlDialect::binaryParameter($pdo, ':ciphertext') . ",
            " . SqlDialect::binaryParameter($pdo, ':nonce') . ",
            :key_version,
            'available'
         )",
    );

    foreach ($offers as $offerValue) {
        $offerId = (int) $offerValue;
        $availableCount->execute(['offer_id' => $offerId]);
        $available = (int) $availableCount->fetchColumn();
        $totalCount->execute(['offer_id' => $offerId]);
        $serial = (int) $totalCount->fetchColumn();

        while ($available < $targetAvailableKeysPerOffer) {
            $serial++;
            $plaintext = sprintf(
                'GAMEHUB-DEMO-NOT-REDEEMABLE-OFFER-%d-%04d',
                $offerId,
                $serial,
            );
            $encrypted = $cipher->encrypt($plaintext, $offerId);
            $insert->execute([
                'offer_id' => $offerId,
                'ciphertext' => SqlDialect::binaryParameterValue($pdo, $encrypted['ciphertext']),
                'nonce' => SqlDialect::binaryParameterValue($pdo, $encrypted['nonce']),
                'key_version' => $encrypted['keyVersion'],
            ]);
            $available++;
            $created++;
        }
    }

    $pdo->commit();
    fwrite(
        STDOUT,
        sprintf(
            "Seeded %d fictional, non-redeemable keys; each active offer now has at least %d available.\n",
            $created,
            $targetAvailableKeysPerOffer,
        ),
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
