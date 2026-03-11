<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Service\WhatsApp\WhatsAppServiceInterface;
use Psr\Log\LoggerInterface;

class AppointmentNotificationService
{
    public function __construct(
        private WhatsAppServiceInterface $whatsApp,
        private LoggerInterface $logger,
    ) {}

    /**
     * Notifica a barbearia quando um novo agendamento é criado (público ou painel).
     */
    public function notifyShopNewAppointment(Appointment $appointment): void
    {
        $shop = $appointment->getBarber()->getShop();
        $phone = $shop->getPhone();
        if (!$phone || !$this->whatsApp->canSendToShop()) {
            return;
        }

        $date = $appointment->getDate()->format('d/m/Y');
        $time = $appointment->getTime()->format('H:i');
        $clientName = $appointment->getClientName() ?? 'Cliente';
        $serviceName = $appointment->getService()?->getName() ?? 'Serviço';
        $barberName = $appointment->getBarber()?->getName() ?? 'Barbeiro';

        $message = "🆕 *Novo agendamento*\n\n";
        $message .= "Cliente: *{$clientName}*\n";
        $message .= "Data: {$date} às {$time}\n";
        $message .= "Serviço: {$serviceName}\n";
        $message .= "Barbeiro: {$barberName}\n";
        $message .= "Status: pendente de confirmação.";

        $this->sendSafe($shop, $phone, $message, 'notifyShopNewAppointment', true);
    }

    /**
     * Notifica o cliente quando a barbearia confirma o agendamento.
     */
    public function notifyClientAppointmentConfirmed(Appointment $appointment): void
    {
        $phone = $appointment->getPhone();
        $shop = $appointment->getBarber()->getShop();
        if (!$phone || !$this->whatsApp->isEnabled($shop)) {
            return;
        }

        $shopName = $appointment->getBarber()->getShop()->getName();
        $date = $appointment->getDate()->format('d/m/Y');
        $time = $appointment->getTime()->format('H:i');
        $serviceName = $appointment->getService()?->getName() ?? 'serviço';

        $message = "✅ *Agendamento confirmado!*\n\n";
        $message .= "Olá! A *{$shopName}* confirmou seu agendamento.\n\n";
        $message .= "📅 Data: {$date}\n";
        $message .= "🕐 Horário: {$time}\n";
        $message .= "✂️ Serviço: {$serviceName}\n\n";
        $message .= "Te esperamos!";

        $this->sendSafe($shop, $phone, $message, 'notifyClientAppointmentConfirmed', false);
    }

    /**
     * Notifica a barbearia 30 minutos antes do horário do agendamento confirmado.
     */
    public function notifyShopReminder(Appointment $appointment): void
    {
        $shop = $appointment->getBarber()->getShop();
        $phone = $shop->getPhone();
        if (!$phone || !$this->whatsApp->canSendToShop()) {
            return;
        }

        $date = $appointment->getDate()->format('d/m/Y');
        $time = $appointment->getTime()->format('H:i');
        $clientName = $appointment->getClientName() ?? 'Cliente';
        $serviceName = $appointment->getService()?->getName() ?? 'Serviço';
        $barberName = $appointment->getBarber()?->getName() ?? 'Barbeiro';

        $message = "⏰ *Lembrete – Agendamento em 30 min*\n\n";
        $message .= "Cliente: *{$clientName}*\n";
        $message .= "Horário: {$date} às {$time}\n";
        $message .= "Serviço: {$serviceName}\n";
        $message .= "Barbeiro: {$barberName}";

        $this->sendSafe($shop, $phone, $message, 'notifyShopReminder', true);
    }

    /**
     * Notifica o cliente 30 minutos antes do horário do agendamento confirmado.
     * Inclui dados da barbearia para o cliente.
     */
    public function notifyClientReminder(Appointment $appointment): void
    {
        $phone = $appointment->getPhone();
        $shop = $appointment->getBarber()->getShop();
        if (!$phone || !$this->whatsApp->isEnabled($shop)) {
            return;
        }

        $shopName = $shop->getName();
        $date = $appointment->getDate()->format('d/m/Y');
        $time = $appointment->getTime()->format('H:i');
        $serviceName = $appointment->getService()?->getName() ?? 'Serviço';
        $barberName = $appointment->getBarber()?->getName() ?? 'Barbeiro';

        $message = "⏰ *Lembrete – Agendamento em 30 min*\n\n";
        $message .= "Olá! A *{$shopName}* te espera em breve.\n\n";
        $message .= "📅 Data: {$date}\n";
        $message .= "🕐 Horário: {$time}\n";
        $message .= "✂️ Serviço: {$serviceName}\n";
        $message .= "👤 Profissional: {$barberName}\n\n";
        $message .= "Te esperamos!";

        $this->sendSafe($shop, $phone, $message, 'notifyClientReminder', false);
    }

    /**
     * Notifica o cliente quando o agendamento é cancelado.
     */
    public function notifyClientAppointmentCancelled(Appointment $appointment): void
    {
        $phone = $appointment->getPhone();
        $shop = $appointment->getBarber()->getShop();
        if (!$phone || !$this->whatsApp->isEnabled($shop)) {
            return;
        }

        $shopName = $shop->getName();
        $date = $appointment->getDate()->format('d/m/Y');
        $time = $appointment->getTime()->format('H:i');
        $serviceName = $appointment->getService()?->getName() ?? 'serviço';

        $message = "❌ *Agendamento cancelado*\n\n";
        $message .= "Olá! Seu agendamento na *{$shopName}* foi cancelado.\n\n";
        $message .= "📅 Era: {$date} às {$time}\n";
        $message .= "✂️ Serviço: {$serviceName}\n\n";
        $message .= "Para agendar novamente, acesse o link da barbearia.";

        $this->sendSafe($shop, $phone, $message, 'notifyClientAppointmentCancelled', false);
    }

    private function sendSafe(?\App\Entity\Shop $shop, string $phone, string $message, string $context, bool $toShop = false): void
    {
        if (!$shop) {
            return;
        }
        try {
            $this->whatsApp->sendText($shop, $phone, $message, $toShop);
            $this->logger->info('WhatsApp enviado', ['context' => $context, 'phone' => substr($phone, -4) . '****']);
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao enviar WhatsApp: ' . $e->getMessage(), [
                'context' => $context,
                'exception' => $e,
            ]);
        }
    }
}
