<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuthRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{id: int, email: string, password_hash: string, display_name: string, status: string}|null
     */
    public function userByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, password_hash, display_name, status
             FROM users
             WHERE email = :email AND role = \'customer\'
             LIMIT 1',
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user === false) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'password_hash' => (string) $user['password_hash'],
            'display_name' => (string) $user['display_name'],
            'status' => (string) $user['status'],
        ];
    }

    public function createUser(string $email, string $passwordHash, string $displayName): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name, role, status)
             VALUES (:email, :password_hash, :display_name, \'customer\', \'active\')',
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createSession(int $userId, string $tokenHash, string $csrfTokenHash): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sessions (
                user_id, token_hash, csrf_token_hash, expires_at, last_seen_at
             )
             VALUES (
                :user_id,
                :token_hash,
                :csrf_token_hash,
                DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1209600 SECOND),
                UTC_TIMESTAMP(6)
             )',
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'csrf_token_hash' => $csrfTokenHash,
        ]);
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
    public function sessionByTokenHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    session.id,
    session.user_id,
    session.csrf_token_hash,
    account.email,
    account.display_name
FROM sessions AS session
JOIN users AS account ON account.id = session.user_id
WHERE session.token_hash = :token_hash
  AND session.revoked_at IS NULL
  AND session.expires_at > UTC_TIMESTAMP(6)
  AND account.status = 'active'
  AND account.role = 'customer'
LIMIT 1
SQL);
        $statement->execute(['token_hash' => $tokenHash]);
        $session = $statement->fetch();
        if ($session === false) {
            return null;
        }

        $touch = $this->pdo->prepare(
            'UPDATE sessions SET last_seen_at = UTC_TIMESTAMP(6) WHERE id = :id',
        );
        $touch->execute(['id' => $session['id']]);

        return [
            'id' => (int) $session['id'],
            'user_id' => (int) $session['user_id'],
            'email' => (string) $session['email'],
            'display_name' => (string) $session['display_name'],
            'csrf_token_hash' => (string) $session['csrf_token_hash'],
        ];
    }

    public function updateCsrfTokenHash(int $sessionId, string $csrfTokenHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sessions
             SET csrf_token_hash = :csrf_token_hash
             WHERE id = :id AND revoked_at IS NULL',
        );
        $statement->execute([
            'csrf_token_hash' => $csrfTokenHash,
            'id' => $sessionId,
        ]);
    }

    public function revokeSession(string $tokenHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sessions
             SET revoked_at = UTC_TIMESTAMP(6)
             WHERE token_hash = :token_hash AND revoked_at IS NULL',
        );
        $statement->execute(['token_hash' => $tokenHash]);
    }

    public function incrementRateLimit(string $action, string $subjectHash): int
    {
        $windowSeconds = match ($action) {
            'login' => 900,
            'register' => 3600,
            'search' => 60,
            default => throw new \LogicException('Unsupported rate-limit action.'),
        };
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO auth_rate_limits (
                action, subject_hash, window_started_at, attempt_count
             )
             VALUES (:action, :subject_hash, UTC_TIMESTAMP(6), 1)
             ON DUPLICATE KEY UPDATE
                attempt_count = IF(
                    window_started_at <= UTC_TIMESTAMP(6) - INTERVAL %d SECOND,
                    1,
                    attempt_count + 1
                ),
                window_started_at = IF(
                    window_started_at <= UTC_TIMESTAMP(6) - INTERVAL %d SECOND,
                    UTC_TIMESTAMP(6),
                    window_started_at
                )',
            $windowSeconds,
            $windowSeconds,
        ));
        $statement->execute([
            'action' => $action,
            'subject_hash' => $subjectHash,
        ]);

        $count = $this->pdo->prepare(
            'SELECT attempt_count
             FROM auth_rate_limits
             WHERE action = :action AND subject_hash = :subject_hash',
        );
        $count->execute([
            'action' => $action,
            'subject_hash' => $subjectHash,
        ]);

        return (int) $count->fetchColumn();
    }
}
