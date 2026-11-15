<?php

declare(strict_types=1);

namespace App\Security;

final class SessionCookies
{
    public const SESSION_NAME = 'gamehub_session';
    public const CSRF_NAME = 'gamehub_csrf';
    public const GUEST_CART_NAME = 'gamehub_guest_cart';

    public static function sessionToken(): ?string
    {
        $token = $_COOKIE[self::SESSION_NAME] ?? null;
        return is_string($token) ? $token : null;
    }

    public static function csrfToken(): ?string
    {
        $token = $_COOKIE[self::CSRF_NAME] ?? null;
        return is_string($token) ? $token : null;
    }

    public static function guestCartToken(): ?string
    {
        $token = $_COOKIE[self::GUEST_CART_NAME] ?? null;
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1
            ? $token
            : null;
    }

    public static function setSession(string $token, int $expiresAt): void
    {
        setcookie(self::SESSION_NAME, $token, self::options($expiresAt, true));
    }

    public static function setCsrf(string $token, int $expiresAt): void
    {
        setcookie(self::CSRF_NAME, $token, self::options($expiresAt, true));
    }

    public static function setGuestCart(string $token, int $expiresAt): void
    {
        setcookie(self::GUEST_CART_NAME, $token, self::options($expiresAt, true));
    }

    public static function clearGuestCart(): void
    {
        setcookie(self::GUEST_CART_NAME, '', self::options(time() - 3600, true));
    }

    public static function clear(): void
    {
        $expired = self::options(time() - 3600, true);
        setcookie(self::SESSION_NAME, '', $expired);
        setcookie(self::CSRF_NAME, '', $expired);
    }

    private static function options(int $expiresAt, bool $httpOnly): array
    {
        $secureSetting = getenv('SESSION_COOKIE_SECURE');
        if ($secureSetting === false) {
            $secure = isset($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== ''
                && $_SERVER['HTTPS'] !== 'off';
        } else {
            $secure = filter_var(
                $secureSetting,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );
            if ($secure === null) {
                throw new \RuntimeException('SESSION_COOKIE_SECURE must be a boolean.');
            }
        }
        if (getenv('APP_ENV') === 'production') {
            $secure = true;
        }

        return [
            'expires' => $expiresAt,
            'path' => '/api/v1',
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $secure ? 'None' : 'Lax',
        ];
    }
}
