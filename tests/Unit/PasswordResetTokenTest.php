<?php

namespace App\Tests\Unit;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    public function testTokenIsSingleUseAndExpires(): void
    {
        $user = new User();
        $valid = new PasswordResetToken($user, str_repeat('a', 64), new \DateTimeImmutable('+1 minute'));
        self::assertTrue($valid->isValid());
        $valid->markUsed();
        self::assertFalse($valid->isValid());

        $expired = new PasswordResetToken($user, str_repeat('b', 64), new \DateTimeImmutable('-1 minute'));
        self::assertFalse($expired->isValid());
    }
}
