<?php

namespace App\Command;

use App\Repository\AppointmentRepository;
use App\Service\AppointmentNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:appointment-reminders',
    description: 'Envia lembretes WhatsApp à barbearia para agendamentos confirmados que ocorrem em ~30 min.',
)]
class SendAppointmentRemindersCommand extends Command
{
    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private AppointmentNotificationService $appointmentNotification,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'window-start',
                null,
                InputOption::VALUE_OPTIONAL,
                'Minutos a partir de agora para início da janela (padrão: 25)',
                25
            )
            ->addOption(
                'window-end',
                null,
                InputOption::VALUE_OPTIONAL,
                'Minutos a partir de agora para fim da janela (padrão: 35)',
                35
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $windowStart = (int) $input->getOption('window-start');
        $windowEnd = (int) $input->getOption('window-end');

        $appointments = $this->appointmentRepository->findConfirmedInNextMinutesWithoutReminder($windowStart, $windowEnd);

        if (count($appointments) === 0) {
            $io->comment('Nenhum agendamento na janela de lembrete (30 min).');
            return Command::SUCCESS;
        }

        foreach ($appointments as $appointment) {
            $this->appointmentNotification->notifyShopReminder($appointment);
            $appointment->setReminderSentAt(new \DateTimeImmutable());
        }
        $this->entityManager->flush();

        $io->success(sprintf('Lembrete enviado para %d agendamento(s).', count($appointments)));
        return Command::SUCCESS;
    }
}
