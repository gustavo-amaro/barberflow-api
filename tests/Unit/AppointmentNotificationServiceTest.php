<?php

namespace App\Tests\Unit;

use App\Entity\Appointment;
use App\Entity\Barber;
use App\Entity\Service;
use App\Entity\Shop;
use App\Service\AppointmentNotificationService;
use App\Service\WhatsApp\WhatsAppServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AppointmentNotificationServiceTest extends TestCase
{
    public function testNewAppointmentNotifiesShopAndAssignedBarber(): void
    {
        $whatsApp = new RecordingWhatsAppService();
        $notification = new AppointmentNotificationService($whatsApp, new NullLogger());
        $appointment = $this->appointment('38999990000', '38988880000');

        $notification->notifyShopNewAppointment($appointment, true);

        self::assertSame(['38999990000', '38988880000'], array_column($whatsApp->calls, 'phone'));
        self::assertSame([true, true], array_column($whatsApp->calls, 'toShop'));
        self::assertStringContainsString('Novo agendamento', $whatsApp->calls[1]['message']);
        self::assertStringContainsString('Barbeiro: Zezé', $whatsApp->calls[1]['message']);
    }

    public function testReminderNotifiesBarberEvenWhenShopHasNoPhone(): void
    {
        $whatsApp = new RecordingWhatsAppService();
        $notification = new AppointmentNotificationService($whatsApp, new NullLogger());
        $appointment = $this->appointment(null, '38988880000');

        $notification->notifyShopReminder($appointment);

        self::assertCount(1, $whatsApp->calls);
        self::assertSame('38988880000', $whatsApp->calls[0]['phone']);
        self::assertStringContainsString('Agendamento em 30 min', $whatsApp->calls[0]['message']);
    }

    public function testSameShopAndBarberPhoneIsNotNotifiedTwice(): void
    {
        $whatsApp = new RecordingWhatsAppService();
        $notification = new AppointmentNotificationService($whatsApp, new NullLogger());
        $appointment = $this->appointment('(38) 99999-0000', '+55 38 99999-0000');

        $notification->notifyShopNewAppointment($appointment);
        $notification->notifyShopReminder($appointment);

        self::assertCount(2, $whatsApp->calls);
    }

    private function appointment(?string $shopPhone, ?string $barberPhone): Appointment
    {
        $shop = (new Shop())
            ->setName('Barbearia Teste')
            ->setSlug('barbearia-teste')
            ->setPhone($shopPhone);
        $barber = (new Barber())
            ->setName('Zezé')
            ->setPhone($barberPhone)
            ->setShop($shop);
        $service = (new Service())
            ->setName('Corte clássico')
            ->setDuration(30)
            ->setPrice('50.00')
            ->setShop($shop);

        return (new Appointment())
            ->setBarber($barber)
            ->setService($service)
            ->setClientName('João')
            ->setPhone('38977770000')
            ->setDate(new \DateTime('2026-07-23'))
            ->setTime(new \DateTime('15:30'))
            ->setPrice('50.00');
    }
}

final class RecordingWhatsAppService implements WhatsAppServiceInterface
{
    /** @var list<array{phone: string, message: string, toShop: bool}> */
    public array $calls = [];

    public function sendText(Shop $shop, string $phone, string $message, bool $toShop = false): bool
    {
        $this->calls[] = compact('phone', 'message', 'toShop');

        return true;
    }

    public function isEnabled(Shop $shop): bool
    {
        return true;
    }

    public function canSendToShop(): bool
    {
        return true;
    }
}
