<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Client;
use App\Entity\Shop;
use App\Entity\ShopSchedule;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\ShopScheduleRepository;
use App\Service\AppointmentNotificationService;
use App\Repository\BarberRepository;
use App\Repository\ClientRepository;
use App\Repository\ServiceRepository;
use App\Repository\ShopRepository;
use App\Service\AppointmentSlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/shops')]
class ShopController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShopRepository $shopRepository,
        private ServiceRepository $serviceRepository,
        private BarberRepository $barberRepository,
        private AppointmentRepository $appointmentRepository,
        private ClientRepository $clientRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private AppointmentNotificationService $appointmentNotification,
        private AppointmentSlotService $slotService,
        private ShopScheduleRepository $scheduleRepository
    ) {}

    #[Route('', name: 'api_shop_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check if user already has a shop
        if ($user->getShop()) {
            return $this->json(['error' => 'Usuário já possui uma barbearia'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        // Check if slug already exists
        if ($this->shopRepository->findBySlug($data['slug'] ?? '')) {
            return $this->json(['error' => 'Slug já está em uso'], Response::HTTP_CONFLICT);
        }

        $shop = new Shop();
        $shop->setName($data['name'] ?? '');
        $shop->setSlug($data['slug'] ?? '');
        $shop->setLogo($data['logo'] ?? null);
        $shop->setPhone($data['phone'] ?? null);
        $shop->setInstagram($data['instagram'] ?? null);
        $shop->setOwner($user);

        // Validate
        $errors = $this->validator->validate($shop);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($shop);
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($shop, null, ['groups' => 'shop:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('/schedule', name: 'api_shop_schedule_show', methods: ['GET'])]
    public function scheduleShow(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $existing = $this->scheduleRepository->findByShopOrderedByDay($shop);
        $scheduleConfigured = count($existing) > 0;
        $byDay = [];
        foreach ($existing as $s) {
            $byDay[$s->getDayOfWeek()] = $s;
        }
        $schedule = [];
        for ($day = 0; $day <= 6; $day++) {
            $s = $byDay[$day] ?? null;
            $schedule[] = [
                'dayOfWeek' => $day,
                'isOpen' => $s ? $s->isOpen() : ($day >= 1 && $day <= 5),
                'timeOpen' => $s && $s->getTimeOpen() ? $s->getTimeOpen()->format('H:i') : '09:00',
                'timeClose' => $s && $s->getTimeClose() ? $s->getTimeClose()->format('H:i') : '18:00',
            ];
        }
        return $this->json(['schedule' => $schedule, 'scheduleConfigured' => $scheduleConfigured]);
    }

    #[Route('/schedule', name: 'api_shop_schedule_update', methods: ['PUT', 'PATCH'])]
    public function scheduleUpdate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $items = $data['schedule'] ?? $data ?? [];
        if (!\is_array($items) || count($items) < 7) {
            return $this->json(['error' => 'Envie os 7 dias (domingo a sábado) em schedule'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->scheduleRepository->findByShopOrderedByDay($shop);
        $byDay = [];
        foreach ($existing as $s) {
            $byDay[$s->getDayOfWeek()] = $s;
        }

        for ($day = 0; $day <= 6; $day++) {
            $item = null;
            foreach ($items as $i) {
                if ((int) ($i['dayOfWeek'] ?? -1) === $day) {
                    $item = $i;
                    break;
                }
            }
            if ($item === null) {
                continue;
            }
            $isOpen = (bool) ($item['isOpen'] ?? true);
            $timeOpen = $item['timeOpen'] ?? '09:00';
            $timeClose = $item['timeClose'] ?? '18:00';
            $schedule = $byDay[$day] ?? null;
            if (!$schedule) {
                $schedule = new ShopSchedule();
                $schedule->setShop($shop);
                $schedule->setDayOfWeek($day);
                $this->entityManager->persist($schedule);
            }
            $schedule->setIsOpen($isOpen);
            try {
                $schedule->setTimeOpen($isOpen ? new \DateTime($timeOpen) : null);
                $schedule->setTimeClose($isOpen ? new \DateTime($timeClose) : null);
            } catch (\Exception) {
                $schedule->setTimeOpen(new \DateTime('09:00'));
                $schedule->setTimeClose(new \DateTime('18:00'));
            }
        }
        $this->entityManager->flush();
        return $this->scheduleShow();
    }

    #[Route('/closed-dates', name: 'api_shop_closed_dates_update', methods: ['PUT', 'PATCH'])]
    public function closedDatesUpdate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        $normalized = $this->normalizeClosedDatesInput($data['closedDates'] ?? []);
        if ($normalized === null) {
            return $this->json(
                ['error' => 'Lista closedDates inválida. Use datas no formato AAAA-MM-DD (máx. 400 datas).'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $shop->setClosedDates($normalized);
        $this->entityManager->flush();

        return $this->json([
            'closedDates' => $shop->getClosedDates(),
        ]);
    }

    #[Route('', name: 'api_shop_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($shop, null, ['groups' => 'shop:read'])
        );
    }

    #[Route('', name: 'api_shop_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        // Check slug uniqueness if changing
        if (isset($data['slug']) && $data['slug'] !== $shop->getSlug()) {
            if ($this->shopRepository->findBySlug($data['slug'])) {
                return $this->json(['error' => 'Slug já está em uso'], Response::HTTP_CONFLICT);
            }
            $shop->setSlug($data['slug']);
        }

        if (isset($data['name'])) {
            $shop->setName($data['name']);
        }
        if (array_key_exists('logo', $data)) {
            $shop->setLogo($data['logo']);
        }
        if (array_key_exists('phone', $data)) {
            $shop->setPhone($data['phone']);
        }
        if (array_key_exists('instagram', $data)) {
            $shop->setInstagram($data['instagram']);
        }
        if (array_key_exists('autoConfirmAppointments', $data)) {
            $shop->setAutoConfirmAppointments((bool) $data['autoConfirmAppointments']);
        }

        // Validate
        $errors = $this->validator->validate($shop);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($shop, null, ['groups' => 'shop:read'])
        );
    }

    #[Route('/public/{slug}', name: 'api_shop_public', methods: ['GET'])]
    public function publicShow(string $slug): JsonResponse
    {
        $shop = $this->shopRepository->findBySlug($slug);

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'slug' => $shop->getSlug(),
            'logo' => $shop->getLogo(),
            'phone' => $shop->getPhone(),
            'instagram' => $shop->getInstagram(),
        ]);
    }

    /**
     * Dados públicos da página de agendamento (shop + serviços + equipe).
     */
    #[Route('/public/{slug}/page', name: 'api_shop_public_page', methods: ['GET'])]
    public function publicPage(string $slug): JsonResponse
    {
        $shop = $this->shopRepository->findBySlug($slug);

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $services = $this->serviceRepository->findActiveByShop($shop);
        $barbers = $this->barberRepository->findActiveByShop($shop);

        return $this->json([
            'shop' => [
                'id' => $shop->getId(),
                'name' => $shop->getName(),
                'slug' => $shop->getSlug(),
                'logo' => $shop->getLogo(),
                'phone' => $shop->getPhone(),
                'instagram' => $shop->getInstagram(),
                'closedDates' => $shop->getClosedDates(),
            ],
            'services' => $this->serializer->normalize($services, null, ['groups' => 'service:read']),
            'barbers' => $this->serializer->normalize($barbers, null, ['groups' => 'barber:read']),
        ]);
    }

    /**
     * Horários disponíveis do barbeiro na data (página pública, sem auth).
     */
    #[Route('/public/{slug}/available-slots', name: 'api_shop_public_available_slots', methods: ['GET'])]
    public function publicAvailableSlots(string $slug, Request $request): JsonResponse
    {
        $shop = $this->shopRepository->findBySlug($slug);
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $barberId = (int) ($request->query->get('barber_id') ?? 0);
        $dateStr = $request->query->get('date');
        $serviceId = $request->query->get('service_id') ? (int) $request->query->get('service_id') : null;
        if (!$barberId || !$dateStr) {
            return $this->json(['error' => 'barber_id e date são obrigatórios'], Response::HTTP_BAD_REQUEST);
        }

        $barber = $this->barberRepository->find($barberId);
        if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        try {
            $date = new \DateTime($dateStr);
        } catch (\Exception) {
            return $this->json(['error' => 'Data inválida'], Response::HTTP_BAD_REQUEST);
        }

        $durationMinutes = null;
        if ($serviceId) {
            $service = $this->serviceRepository->find($serviceId);
            if ($service && $service->getShop()->getId() === $shop->getId()) {
                $durationMinutes = $service->getDuration();
            }
        }

        $slots = $this->slotService->getSlotsForBarberAndDate($barber, $date, $durationMinutes);
        return $this->json(['slots' => $slots]);
    }

    /**
     * Criar agendamento pela página pública (sem autenticação).
     */
    #[Route('/public/{slug}/appointments', name: 'api_shop_public_appointments_create', methods: ['POST'])]
    public function createPublicAppointment(string $slug, Request $request): JsonResponse
    {
        $shop = $this->shopRepository->findBySlug($slug);

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        $barber = $this->barberRepository->find($data['barber_id'] ?? 0);
        if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $service = $this->serviceRepository->find($data['service_id'] ?? 0);
        if (!$service || $service->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Serviço não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $aptDate = new \DateTime($data['date'] ?? 'today');
        $aptTime = new \DateTime($data['time'] ?? 'now');
        if ($shop->isClosedOnDate($aptDate)) {
            return $this->json(
                ['error' => 'Não há atendimento nesta data (feriado ou dia fechado). Escolha outra data.'],
                Response::HTTP_BAD_REQUEST
            );
        }
        if ($this->slotService->isPast($aptDate, $aptTime)) {
            return $this->json(
                ['error' => 'Não é possível agendar horário no passado. Escolha uma data e horário futuros.'],
                Response::HTTP_BAD_REQUEST
            );
        }
        $serviceDuration = $service->getDuration() ?? 30;

        if ($this->slotService->hasOverlap($barber, $aptDate, $aptTime, $serviceDuration)) {
            return $this->json(
                ['error' => 'Este horário já está ocupado. Escolha outro horário ou profissional.'],
                Response::HTTP_CONFLICT
            );
        }

        $client = null;
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
        $clientName = isset($data['client_name']) ? trim((string) $data['client_name']) : '';
        if ($phone !== null && $phone !== '') {
            $client = $this->clientRepository->findOneByShopAndPhoneNormalized($shop, $phone);
            if (!$client) {
                $client = new Client();
                $client->setShop($shop);
                $client->setName($clientName !== '' ? $clientName : 'Cliente');
                $client->setPhone(preg_replace('/\D/', '', $phone));
                $this->entityManager->persist($client);
            }
        }

        $appointment = new Appointment();
        $appointment->setBarber($barber);
        $appointment->setService($service);
        $appointment->setClient($client);
        $appointment->setClientName($clientName !== '' ? $clientName : ($client ? $client->getName() : 'Cliente'));
        $appointment->setPhone($data['phone'] ?? ($client ? $client->getPhone() : null));
        $appointment->setDate($aptDate);
        $appointment->setTime($aptTime);
        $initialStatus = $data['status'] ?? null;
        $wasAutoConfirmed = false;
        if ($initialStatus !== null) {
            $appointment->setStatus($initialStatus);
        } else {
            $wasAutoConfirmed = $shop->isAutoConfirmAppointments();
            $appointment->setStatus($wasAutoConfirmed ? Appointment::STATUS_CONFIRMED : Appointment::STATUS_PENDING);
        }
        $appointment->setPrice($data['price'] ?? $service->getPrice());

        $errors = $this->validator->validate($appointment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($appointment);
        $this->entityManager->flush();

        if ($wasAutoConfirmed) {
            $this->appointmentNotification->notifyShopNewAppointment($appointment, true);
            $this->appointmentNotification->notifyClientAppointmentConfirmed($appointment);
        } else {
            $this->appointmentNotification->notifyShopNewAppointment($appointment);
        }

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read']),
            Response::HTTP_CREATED
        );
    }

    /**
     * Listar agendamentos do cliente por telefone (página pública, sem auth).
     * Retorna apenas agendamentos futuros/hoje e não cancelados.
     */
    #[Route('/public/{slug}/appointments', name: 'api_shop_public_appointments_index', methods: ['GET'])]
    public function listPublicAppointments(string $slug, Request $request): JsonResponse
    {
        $shop = $this->shopRepository->findBySlug($slug);
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $phone = $request->query->get('phone', '');
        $phoneNormalized = preg_replace('/\D/', '', $phone);
        if ($phoneNormalized === '') {
            return $this->json(['appointments' => []]);
        }

        $all = $this->appointmentRepository->findUpcomingByShop($shop);
        $filtered = array_filter($all, function (Appointment $a) use ($phoneNormalized) {
            $aptPhone = $a->getPhone();
            return $aptPhone && preg_replace('/\D/', '', (string) $aptPhone) === $phoneNormalized;
        });

        return $this->json([
            'appointments' => $this->serializer->normalize(array_values($filtered), null, ['groups' => 'appointment:read']),
        ]);
    }

    /**
     * Cancelar agendamento pela página pública (sem auth).
     * Corpo: { "phone": "..." }. O telefone deve coincidir com o do agendamento.
     */
    #[Route('/public/{slug}/appointments/{id}/cancel', name: 'api_shop_public_appointment_cancel', methods: ['POST'])]
    public function cancelPublicAppointment(string $slug, int $id, Request $request): JsonResponse
    {
        $shop = $this->shopRepository->findBySlug($slug);
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointment = $this->appointmentRepository->find($id);
        if (!$appointment || $appointment->getBarber()->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Agendamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        if ($appointment->getStatus() === Appointment::STATUS_CANCELLED) {
            return $this->json(['error' => 'Este agendamento já foi cancelado'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $phone = (string) ($data['phone'] ?? '');
        $phoneNormalized = preg_replace('/\D/', '', $phone);
        $aptPhone = $appointment->getPhone();
        if (!$aptPhone || preg_replace('/\D/', '', (string) $aptPhone) !== $phoneNormalized) {
            return $this->json(['error' => 'Telefone não confere com o agendamento'], Response::HTTP_FORBIDDEN);
        }

        $appointment->cancel();
        $this->entityManager->flush();
        $this->appointmentNotification->notifyClientAppointmentCancelled($appointment);

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read'])
        );
    }

    /**
     * @return list<string>|null
     */
    private function normalizeClosedDatesInput(mixed $raw): ?array
    {
        if (!\is_array($raw)) {
            return null;
        }
        if (\count($raw) > 400) {
            return null;
        }
        $seen = [];
        foreach ($raw as $item) {
            if (!\is_string($item)) {
                return null;
            }
            $item = trim($item);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $item)) {
                return null;
            }
            $parts = explode('-', $item);
            $y = (int) $parts[0];
            $m = (int) $parts[1];
            $d = (int) $parts[2];
            if (!checkdate($m, $d, $y)) {
                return null;
            }
            $seen[$item] = true;
        }
        $out = array_keys($seen);
        sort($out);

        return $out;
    }
}
