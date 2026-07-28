<?php

namespace App\Service;

use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class PasswordResetRequestLimiter
{
    public function __construct(
        private RateLimiterFactory $limiter,
    ) {
    }

    public function consume(string $email): RateLimit
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $anonymousKey = hash('sha256', $normalizedEmail);

        return $this->limiter->create($anonymousKey)->consume();
    }
}
