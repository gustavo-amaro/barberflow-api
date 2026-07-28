<?php

namespace App\Tests\Unit;

use App\Service\PasswordResetRequestLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class PasswordResetRequestLimiterTest extends TestCase
{
    public function testItAllowsOnlyOneRequestPerNormalizedEmailDuringTheInterval(): void
    {
        $factory = new RateLimiterFactory([
            'id' => 'password_reset',
            'policy' => 'fixed_window',
            'limit' => 1,
            'interval' => '1 minute',
        ], new InMemoryStorage());
        $limiter = new PasswordResetRequestLimiter($factory);

        self::assertTrue($limiter->consume('cliente@example.com')->isAccepted());
        self::assertFalse($limiter->consume('  CLIENTE@example.com ')->isAccepted());
        self::assertTrue($limiter->consume('outro@example.com')->isAccepted());
    }
}
