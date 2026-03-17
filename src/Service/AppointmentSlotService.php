<?php

namespace App\Service;

use App\Entity\Barber;
use App\Repository\AppointmentRepository;
use App\Repository\ShopScheduleRepository;

class AppointmentSlotService
{
    /** Intervalo em minutos entre cada slot (15 em 15 minutos). */
    private const SLOT_INTERVAL = 15;

    /** Horário padrão início (quando barbeiro não tem workStart). */
    private const DEFAULT_START = '08:00';

    /** Horário padrão fim (quando barbeiro não tem workEnd). */
    private const DEFAULT_END = '18:00';

    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private ShopScheduleRepository $shopScheduleRepository
    ) {}

    /**
     * Retorna lista de slots para o barbeiro na data informada (de 15 em 15 minutos).
     * Considera: horário de funcionamento da barbearia no dia da semana,
     * horário do barbeiro, ocupados (bloqueando a duração de cada agendamento) e horários no passado.
     * Se $durationMinutes for informado (ex: duração do serviço), um slot só é disponível se
     * couber o serviço inteiro a partir daquele horário (próximos durationMinutes não ocupados).
     *
     * @param int|null $durationMinutes Duração em minutos do serviço a agendar; null = apenas lista slots (admin).
     * @return array<int, array{time: string, available: bool}>
     */
    public function getSlotsForBarberAndDate(Barber $barber, \DateTimeInterface $date, ?int $durationMinutes = null): array
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

        $occupied = $this->getOccupiedSlotsByDuration($barber, $date);
        $now = new \DateTime('now');
        $isToday = $date->format('Y-m-d') === $now->format('Y-m-d');

        $result = [];
        foreach ($slots as $time) {
            $available = true;

            if ($durationMinutes !== null) {
                // Serviço precisa caber a partir deste slot: todos os blocos de 15 min até slot+duration devem estar livres
                $slotEnd = $this->addMinutesToTime($time, $durationMinutes);
                if ($this->timeToMinutes($slotEnd) > $this->timeToMinutes($end)) {
                    $available = false;
                } else {
                    for ($offset = 0; $offset < $durationMinutes; $offset += self::SLOT_INTERVAL) {
                        $checkTime = $this->addMinutesToTime($time, $offset);
                        if (\in_array($checkTime, $occupied, true)) {
                            $available = false;
                            break;
                        }
                    }
                }
            } else {
                if (\in_array($time, $occupied, true)) {
                    $available = false;
                }
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
     * Verifica se já existe agendamento que sobrepõe o horário informado (barbeiro + data + início + duração).
     */
    public function hasOverlap(Barber $barber, \DateTimeInterface $date, \DateTimeInterface $startTime, int $durationMinutes): bool
    {
        $appointments = $this->appointmentRepository->findByBarberAndDate($barber, $date);
        $newStart = (int) $startTime->format('H') * 60 + (int) $startTime->format('i');
        $newEnd = $newStart + $durationMinutes;

        foreach ($appointments as $apt) {
            $aptTime = $apt->getTime();
            if (!$aptTime instanceof \DateTimeInterface) {
                continue;
            }
            $duration = 30;
            if ($apt->getService() !== null) {
                $duration = $apt->getService()->getDuration() ?? 30;
            }
            $aptStart = (int) $aptTime->format('H') * 60 + (int) $aptTime->format('i');
            $aptEnd = $aptStart + $duration;
            if ($newStart < $aptEnd && $aptStart < $newEnd) {
                return true;
            }
        }
        return false;
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
     * Slots de 15 min já ocupados pelo barbeiro na data (cada agendamento bloqueia início até início+duração do serviço).
     *
     * @return string[] Lista de "H:i" (cada slot de 15 min que cai dentro de algum agendamento)
     */
    private function getOccupiedSlotsByDuration(Barber $barber, \DateTimeInterface $date): array
    {
        $appointments = $this->appointmentRepository->findByBarberAndDate($barber, $date);
        $occupied = [];
        foreach ($appointments as $apt) {
            $t = $apt->getTime();
            if (!$t instanceof \DateTimeInterface) {
                continue;
            }
            $startMinutes = (int) $t->format('H') * 60 + (int) $t->format('i');
            $duration = 30;
            if ($apt->getService() !== null) {
                $duration = $apt->getService()->getDuration() ?? 30;
            }
            $endMinutes = $startMinutes + $duration;

            // Marca qualquer slot de 15 min que intersecciona o intervalo [start, end)
            $firstSlot = intdiv($startMinutes, self::SLOT_INTERVAL) * self::SLOT_INTERVAL;
            for ($m = $firstSlot; $m < $endMinutes; $m += self::SLOT_INTERVAL) {
                $occupied[$this->minutesToTime($m)] = true;
            }
        }
        return array_keys($occupied);
    }

    private function timeToMinutes(string $time): int
    {
        $dt = \DateTime::createFromFormat('H:i', $time);
        if (!$dt) {
            return 0;
        }
        return (int) $dt->format('H') * 60 + (int) $dt->format('i');
    }

    private function minutesToTime(int $minutes): string
    {
        $minutes = $minutes % (24 * 60);
        if ($minutes < 0) {
            $minutes += 24 * 60;
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    private function addMinutesToTime(string $time, int $minutes): string
    {
        $dt = \DateTime::createFromFormat('H:i', $time);
        if (!$dt) {
            return $time;
        }
        $dt->modify('+' . $minutes . ' minutes');
        return $dt->format('H:i');
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
