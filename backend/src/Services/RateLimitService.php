<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuthRepository;
use App\Support\ApiException;

final class RateLimitService
{
    private const POLICIES = [
        'login' => 10,
        'register' => 5,
        'search' => 60,
    ];

    public function __construct(private readonly AuthRepository $auth)
    {
    }

    public function assertAllowed(string $action, string $clientIp): void
    {
        $policy = self::POLICIES[$action] ?? null;
        if ($policy === null) {
            throw new \LogicException('Unsupported rate-limit action.');
        }

        $ip = filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : 'unknown';
        $attemptCount = $this->auth->incrementRateLimit($action, hash('sha256', $ip));
        if ($attemptCount > $policy) {
            throw new ApiException(
                429,
                'RATE_LIMITED',
                'Too many requests. Please wait before trying again.',
            );
        }
    }
}
