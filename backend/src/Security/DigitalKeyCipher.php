<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class DigitalKeyCipher
{
    /**
     * @param array<int, string> $keys
     */
    private function __construct(
        private readonly array $keys,
        private readonly int $activeVersion,
    ) {
    }

    public static function fromEnvironment(): self
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('The Sodium extension is required for digital key encryption.');
        }

        $configuredKeys = getenv('KEY_ENCRYPTION_KEYS');
        $activeVersion = getenv('KEY_ENCRYPTION_KEY_VERSION');
        if ($configuredKeys === false || $configuredKeys === '' || $activeVersion === false) {
            throw new RuntimeException('Digital key encryption is not configured.');
        }

        $decodedKeys = json_decode($configuredKeys, true);
        $version = filter_var($activeVersion, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if (!is_array($decodedKeys) || $decodedKeys === [] || $version === false) {
            throw new RuntimeException('Digital key encryption configuration is invalid.');
        }

        $keys = [];
        foreach ($decodedKeys as $keyVersion => $encodedKey) {
            if (
                !is_int($keyVersion)
                || $keyVersion < 1
                || $keyVersion > 65535
                || !is_string($encodedKey)
            ) {
                throw new RuntimeException('Digital key encryption configuration is invalid.');
            }
            $key = base64_decode($encodedKey, true);
            if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new RuntimeException('Every configured digital key encryption key must be 32 bytes.');
            }
            $keys[$keyVersion] = $key;
        }

        if (!isset($keys[$version])) {
            throw new RuntimeException('The active digital key encryption version is not in the key ring.');
        }

        return new self($keys, $version);
    }

    /**
     * @return array{ciphertext: string, nonce: string, keyVersion: int}
     */
    public function encrypt(string $plaintext, int $offerId): array
    {
        $plaintext = trim($plaintext);
        if ($offerId < 1 || $plaintext === '' || strlen($plaintext) > 1024) {
            throw new RuntimeException('A digital key or offer identifier is invalid.');
        }

        $payload = json_encode(
            ['offerId' => $offerId, 'key' => $plaintext],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox(
            $payload,
            $nonce,
            $this->keys[$this->activeVersion],
        );

        if (strlen($ciphertext) > 2048) {
            throw new RuntimeException('The encrypted digital key exceeds the storage limit.');
        }

        return [
            'ciphertext' => $ciphertext,
            'nonce' => $nonce,
            'keyVersion' => $this->activeVersion,
        ];
    }

    public function decrypt(
        string $ciphertext,
        string $nonce,
        int $keyVersion,
        int $offerId,
    ): string {
        $key = $this->keys[$keyVersion] ?? null;
        if (
            $key === null
            || strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        ) {
            throw new RuntimeException('The digital key encryption version or nonce is invalid.');
        }

        $payload = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if (!is_string($payload)) {
            throw new RuntimeException('The digital key could not be authenticated.');
        }

        try {
            $decoded = json_decode($payload, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('The decrypted digital key payload is invalid.', 0, $exception);
        }

        if (
            !is_array($decoded)
            || ($decoded['offerId'] ?? null) !== $offerId
            || !is_string($decoded['key'] ?? null)
            || $decoded['key'] === ''
        ) {
            throw new RuntimeException('The decrypted digital key payload does not match its offer.');
        }

        return $decoded['key'];
    }

    public function verify(
        string $ciphertext,
        string $nonce,
        int $keyVersion,
        int $offerId,
    ): void {
        $plaintext = $this->decrypt($ciphertext, $nonce, $keyVersion, $offerId);
        sodium_memzero($plaintext);
    }
}
