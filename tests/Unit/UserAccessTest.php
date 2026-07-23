<?php

namespace App\Tests\Unit;

use App\Entity\Barber;
use App\Entity\Shop;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserAccessTest extends TestCase
{
    public function testOwnerCanAlsoBeLinkedToBarberWithoutChangingRole(): void
    {
        $owner = (new User())->setName('Dono')->setEmail('dono@example.com')->setAccessRole(User::ACCESS_OWNER);
        $shop = (new Shop())->setName('Barbearia')->setSlug('barbearia')->setOwner($owner);
        $barber = (new Barber())->setName('Dono')->setShop($shop)->setUser($owner);

        self::assertSame($shop, $owner->getShop());
        self::assertSame($barber, $owner->getBarber());
        self::assertSame($owner, $barber->getUser());
        self::assertTrue($owner->isOwner());
        self::assertContains('ROLE_OWNER', $owner->getRoles());
    }

    public function testStaffRoleAndPasswordChangeState(): void
    {
        $staff = (new User())->setAccessRole(User::ACCESS_BARBER)->setMustChangePassword(true)->setActive(true);
        self::assertTrue($staff->isBarberUser());
        self::assertTrue($staff->mustChangePassword());
        self::assertContains('ROLE_BARBER', $staff->getRoles());
        $staff->setMustChangePassword(false)->setActive(false);
        self::assertFalse($staff->mustChangePassword());
        self::assertFalse($staff->isActive());
        self::assertSame(0, $staff->getSessionVersion());
        $staff->revokeSessions();
        self::assertSame(1, $staff->getSessionVersion());
    }
}
