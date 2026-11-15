<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuthRepository;
use App\Support\ApiException;
use App\Support\ValidationError;
use PDO;
use PDOException;
use Throwable;

final class AuthService
{
    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/D';

    private static ?string $dummyPasswordHash = null;
    private readonly RateLimitService $rateLimits;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AuthRepository $auth,
        private readonly CartService $carts,
    ) {
        $this->rateLimits = new RateLimitService($auth);
    }

    /**
     * @param array<string, mixed>|null $session
     */
    public function issueCsrfToken(?array $session, ?string $csrfCookie): string
    {
        if (
            $csrfCookie !== null
            && preg_match(self::TOKEN_PATTERN, $csrfCookie) === 1
            && (
                $session === null
                || hash_equals(
                    $session['csrf_token_hash'],
                    hash('sha256', $csrfCookie),
                )
            )
        ) {
            return $csrfCookie;
        }

        $csrfToken = bin2hex(random_bytes(32));
        if ($session !== null) {
            $this->auth->updateCsrfTokenHash(
                $session['id'],
                hash('sha256', $csrfToken),
            );
        }

        return $csrfToken;
    }

    /**
     * @param array<string, mixed>|null $session
     */
    public function assertCsrf(?string $headerToken, ?string $cookieToken, ?array $session): void
    {
        if (
            $headerToken === null
            || $cookieToken === null
            || preg_match(self::TOKEN_PATTERN, $headerToken) !== 1
            || !hash_equals($cookieToken, $headerToken)
            || (
                $session !== null
                && !hash_equals(
                    $session['csrf_token_hash'],
                    hash('sha256', $headerToken),
                )
            )
        ) {
            throw new ApiException(
                403,
                'CSRF_VALIDATION_FAILED',
                'The request could not be verified. Refresh the page and try again.',
            );
        }
    }

    /**
     * @return array{
     *   id: int,
     *   user_id: int,
     *   email: string,
     *   display_name: string,
     *   csrf_token_hash: string
     * }|null
     */
    public function session(?string $rawToken): ?array
    {
        if ($rawToken === null || preg_match(self::TOKEN_PATTERN, $rawToken) !== 1) {
            return null;
        }

        return $this->auth->sessionByTokenHash(hash('sha256', $rawToken));
    }

    /**
     * @param array<string, mixed> $session
     * @return array{id: int, email: string, displayName: string}
     */
    public function presentSessionUser(array $session): array
    {
        return [
            'id' => (int) $session['user_id'],
            'email' => (string) $session['email'],
            'displayName' => (string) $session['display_name'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   user: array{id: int, email: string, displayName: string},
     *   sessionToken: string,
     *   csrfToken: string
     * }
     */
    public function register(
        array $input,
        string $clientIp,
        ?string $previousSessionToken,
        ?string $guestCartToken = null,
    ): array
    {
        $this->assertFields($input, ['email', 'password', 'displayName']);
        $email = self::email($input['email'] ?? null);
        $password = self::password($input['password'] ?? null);
        $displayName = self::displayName($input['displayName'] ?? null);
        $this->rateLimits->assertAllowed('register', $clientIp);

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($passwordHash)) {
            throw new \RuntimeException('Password hashing failed.');
        }

        try {
            return $this->transaction(function () use (
                $email,
                $passwordHash,
                $displayName,
                $previousSessionToken,
                $guestCartToken,
            ): array {
                $userId = $this->auth->createUser($email, $passwordHash, $displayName);
                if ($guestCartToken !== null) {
                    $this->carts->mergeGuestCart($userId, $guestCartToken);
                }
                $this->revokePreviousSession($previousSessionToken);
                $session = $this->createSession($userId);

                return [
                    'user' => [
                        'id' => $userId,
                        'email' => $email,
                        'displayName' => $displayName,
                    ],
                    ...$session,
                ];
            });
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new ApiException(
                    409,
                    'EMAIL_ALREADY_REGISTERED',
                    'An account with this email already exists.',
                    ['email' => 'Choose another email or sign in.'],
                );
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   user: array{id: int, email: string, displayName: string},
     *   sessionToken: string,
     *   csrfToken: string
     * }
     */
    public function login(
        array $input,
        string $clientIp,
        ?string $previousSessionToken,
        ?string $guestCartToken = null,
    ): array
    {
        $this->assertFields($input, ['email', 'password']);
        $email = self::email($input['email'] ?? null);
        $password = self::password($input['password'] ?? null);
        $this->rateLimits->assertAllowed('login', $clientIp);

        $user = $this->auth->userByEmail($email);
        $hash = $user['password_hash'] ?? self::dummyPasswordHash();
        $passwordMatches = password_verify($password, $hash);
        if (
            $user === null
            || $user['status'] !== 'active'
            || !$passwordMatches
        ) {
            throw new ApiException(401, 'INVALID_CREDENTIALS', 'Email or password is incorrect.');
        }

        return $this->transaction(function () use (
            $user,
            $previousSessionToken,
            $guestCartToken,
        ): array {
            $this->revokePreviousSession($previousSessionToken);
            if ($guestCartToken !== null) {
                $this->carts->mergeGuestCart($user['id'], $guestCartToken);
            }
            $session = $this->createSession($user['id']);

            return [
                'user' => $this->presentUser($user),
                ...$session,
            ];
        });
    }

    public function logout(?string $rawToken): void
    {
        if ($rawToken !== null && preg_match(self::TOKEN_PATTERN, $rawToken) === 1) {
            $this->auth->revokeSession(hash('sha256', $rawToken));
        }
    }

    /**
     * @return array{sessionToken: string, csrfToken: string}
     */
    private function createSession(int $userId): array
    {
        $sessionToken = bin2hex(random_bytes(32));
        $csrfToken = bin2hex(random_bytes(32));
        $this->auth->createSession(
            $userId,
            hash('sha256', $sessionToken),
            hash('sha256', $csrfToken),
        );

        return ['sessionToken' => $sessionToken, 'csrfToken' => $csrfToken];
    }

    private function revokePreviousSession(?string $rawToken): void
    {
        if ($rawToken !== null && preg_match(self::TOKEN_PATTERN, $rawToken) === 1) {
            $this->auth->revokeSession(hash('sha256', $rawToken));
        }
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

    private function assertFields(array $input, array $allowed): void
    {
        $fields = [];
        foreach (array_diff(array_keys($input), $allowed) as $field) {
            $fields[(string) $field] = 'This field is not supported.';
        }
        if ($fields !== []) {
            throw new ValidationError('One or more fields are invalid.', $fields);
        }
    }

    private static function email(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ValidationError('One or more fields are invalid.', [
                'email' => 'Enter a valid email address.',
            ]);
        }

        $email = strtolower(trim($value));
        if (
            strlen($email) > 254
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new ValidationError('One or more fields are invalid.', [
                'email' => 'Enter a valid email address.',
            ]);
        }

        return $email;
    }

    private static function password(mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 12 || strlen($value) > 72) {
            throw new ValidationError('One or more fields are invalid.', [
                'password' => 'Use a password between 12 and 72 bytes.',
            ]);
        }

        return $value;
    }

    private static function displayName(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ValidationError('One or more fields are invalid.', [
                'displayName' => 'Enter a display name.',
            ]);
        }

        $displayName = trim($value);
        if (strlen($displayName) < 2 || strlen($displayName) > 80) {
            throw new ValidationError('One or more fields are invalid.', [
                'displayName' => 'Use a display name between 2 and 80 bytes.',
            ]);
        }

        return $displayName;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{id: int, email: string, displayName: string}
     */
    private function presentUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'displayName' => (string) $user['display_name'],
        ];
    }

    private static function dummyPasswordHash(): string
    {
        if (self::$dummyPasswordHash === null) {
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            if (!is_string($hash)) {
                throw new \RuntimeException('Password hashing failed.');
            }
            self::$dummyPasswordHash = $hash;
        }

        return self::$dummyPasswordHash;
    }
}
