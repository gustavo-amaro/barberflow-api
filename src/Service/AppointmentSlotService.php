<?php

namespace App\Service;

use App\Entity\Barber;
use App\Repository\AppointmentRepository;
use App\Repository\ShopScheduleRepository;

class AppointmentSlotService
{
    /** Intervalo em minutos entre cada slot. */
    private const SLOT_INTERVAL = 30;

    /** Horário padrão início (quando barbeiro não tem workStart). */
    private const DEFAULT_START = '08:00';

    /** Horário padrão fim (quando barbeiro não tem workEnd). */
    private const DEFAULT_END = '18:00';

    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private ShopScheduleRepository $shopScheduleRepository
    ) {}

    /**
     * Retorna lista de slots para o barbeiro na data informada.
     * Considera: horário de funcionamento da barbearia no dia da semana,
     * horário do barbeiro, ocupados e horários no passado.
     * Cada slot tem: time (HH:MM), available (bool).
     *
     * @return array<int, array{time: string, available: bool}>
     */
    public function getSlotsForBarberAndDate(Barber $barber, \DateTimeInterface $date): array
    {
        $barberStart = $this->getStartTime($barber);
        $barberEnd = $this->getEndTime($barber);

        $shop = $barber->getShop();
        $dayOfWeek = (int) $date->format('w'); // 0=domingo a 6=sábado
        $shopSchedule = $shop ? $this->shopScheduleRepository->findOneByShopAndDay($shop, $dayOfWeek) : null;

        if ($shopSchedule !== null && !$shopSchedule->isOpen()) {
            return [];
        }

        if ($shopSchedule !== null && $shopSchedule->getTimeOpen() && $shopSchedule->getTimeClose()) {
            $shopOpen = $shopSchedule->getTimeOpen()->format('H:i');
            $shopClose = $shopSchedule->getTimeClose()->format('H:i');
            $start = $this->timeMax($barberStart, $shopOpen);
            $end = $this->timeMin($barberEnd, $shopClose);
            if ($start >= $end) {
                return [];
            }
        } else {
            $start = $barberStart;
            $end = $barberEnd;
        }

        $slots = $this->generateTimeSlots($start, $end);

        $occupied = $this->getOccupiedTimes($barber, $date);
        $now = new \DateTime('now');
        $isToday = $date->format('Y-m-d') === $now->format('Y-m-d');

        $result = [];
        foreach ($slots as $time) {
            $available = true;
            if (\in_array($time, $occupied, true)) {
                $available = false;
            }
            if ($available && $isToday) {
                $slotDateTime = new \DateTime($date->format('Y-m-d') . ' ' . $time);
                if ($slotDateTime <= $now) {
                    $available = false;
                }
            }
            $result[] = ['time' => $time, 'available' => $available];
        }

        return $result;
    }

    /**
     * Verifica se data+horário não é retroativo.
     */
    public function isPast(\DateTimeInterface $date, \DateTimeInterface $time): bool
    {
        $combined = new \DateTime($date->format('Y-m-d') . ' ' . $time->format('H:i:s'));
        return $combined <= new \DateTime('now');
    }

    private function getStartTime(Barber $barber): string
    {
        $workStart = $barber->getWorkStart();
        if ($workStart instanceof \DateTimeInterface) {
            return $workStart->format('H:i');
        }
        return self::DEFAULT_START;
    }

    private function getEndTime(Barber $barber): string
    {
        $workEnd = $barber->getWorkEnd();
        if ($workEnd instanceof \DateTimeInterface) {
            return $workEnd->format('H:i');
        }
        return self::DEFAULT_END;
    }

    /**
     * @return string[] Lista de horários HH:MM entre start e end (intervalo SLOT_INTERVAL).
     */
    private function generateTimeSlots(string $start, string $end): array
    {
        $slots = [];
        $current = \DateTime::createFromFormat('H:i', $start);
        $endDt = \DateTime::createFromFormat('H:i', $end);
        if (!$current || !$endDt) {
            $current = \DateTime::createFromFormat('H:i', self::DEFAULT_START);
            $endDt = \DateTime::createFromFormat('H:i', self::DEFAULT_END);
        }
        while ($current < $endDt) {
            $slots[] = $current->format('H:i');
            $current->modify('+' . self::SLOT_INTERVAL . ' minutes');
        }
        return $slots;
    }

    /**
     * Horários já ocupados pelo barbeiro na data (não cancelados).
     *
     * @return string[] Lista de "H:i"
     */
    private function getOccupiedTimes(Barber $barber, \DateTimeInterface $date): array
    {
        $appointments = $this->appointmentRepository->findByBarberAndDate($barber, $date);
        $times = [];
        foreach ($appointments as $apt) {
            $t = $apt->getTime();
            if ($t instanceof \DateTimeInterface) {
                $times[] = $t->format('H:i');
            }
        }
        return $times;
    }

    private function timeMax(string $a, string $b): string
    {
        $tA = \DateTime::createFromFormat('H:i', $a);
        $tB = \DateTime::createFromFormat('H:i', $b);
        if (!$tA || !$tB) {
            return $a;
        }
        return $tA >= $tB ? $a : $b;
    }

    private function timeMin(string $a, string $b): string
    {
        $tA = \DateTime::createFromFormat('H:i', $a);
        $tB = \DateTime::createFromFormat('H:i', $b);
        if (!$tA || !$tB) {
            return $a;
        }
        return $tA <= $tB ? $a : $b;
    }
}
