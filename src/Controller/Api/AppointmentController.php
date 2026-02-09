<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Client;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\BarberRepository;
use App\Repository\ClientRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/appointments')]
class AppointmentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AppointmentRepository $appointmentRepository,
        private BarberRepository $barberRepository,
        private ServiceRepository $serviceRepository,
        private ClientRepository $clientRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_appointments_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $date = $request->query->get('date');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $barberId = $request->query->get('barber_id');

        if ($date) {
            $appointments = $this->appointmentRepository->findByShopAndDate(
                $shop,
                new \DateTime($date)
            );
        } elseif ($startDate && $endDate) {
            $appointments = $this->appointmentRepository->findByShopAndDateRange(
                $shop,
                new \DateTime($startDate),
                new \DateTime($endDate)
            );
        } elseif ($barberId) {
            $barber = $this->barberRepository->find($barberId);
            if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
                return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
            }
            $appointments = $this->appointmentRepository->findByBarber($barber);
        } else {
            $appointments = $this->appointmentRepository->findTodayByShop($shop);
        }

        // Auto-complete: confirmados cujo horário já passou viram completed
        $now = new \DateTime('now');
        $changed = false;
        foreach ($appointments as $apt) {
            if ($apt->getStatus() !== Appointment::STATUS_CONFIRMED) {
                continue;
            }
            $aptDateTime = new \DateTime(
                $apt->getDate()->format('Y-m-d') . ' ' . $apt->getTime()->format('H:i:s')
            );
            if ($aptDateTime <= $now) {
                $apt->complete();
                $client = $apt->getClient();
                if ($client) {
                    $client->incrementVisits();
                    $client->addToTotalSpent($apt->getPrice());
                }
                $changed = true;
            }
        }
        if ($changed) {
            $this->entityManager->flush();
        }

        return $this->json(
            $this->serializer->normalize($appointments, null, ['groups' => 'appointment:read'])
        );
    }

    #[Route('/pending', name: 'api_appointments_pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointments = $this->appointmentRepository->findPendingByShop($shop);

        return $this->json(
            $this->serializer->normalize($appointments, null, ['groups' => 'appointment:read'])
        );
    }

    #[Route('', name: 'api_appointments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
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

        // Validate barber
        $barber = $this->barberRepository->find($data['barber_id'] ?? 0);
        if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Validate service
        $service = $this->serviceRepository->find($data['service_id'] ?? 0);
        if (!$service || $service->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Serviço não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $aptDate = new \DateTime($data['date'] ?? 'today');
        $aptTime = new \DateTime($data['time'] ?? 'now');
        $existing = $this->appointmentRepository->findOneByBarberAndDateTime($barber, $aptDate, $aptTime);
        if ($existing) {
            return $this->json(
                ['error' => 'Este barbeiro já possui um agendamento neste horário. Escolha outro horário ou outro profissional.'],
                Response::HTTP_CONFLICT
            );
        }

        // Find or create client (by phone if no client_id)
        $client = null;
        if (isset($data['client_id']) && $data['client_id']) {
            $client = $this->clientRepository->find($data['client_id']);
            if (!$client || $client->getShop()->getId() !== $shop->getId()) {
                return $this->json(['error' => 'Cliente não encontrado'], Response::HTTP_NOT_FOUND);
            }
        } else {
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
        }

        $appointment = new Appointment();
        $appointment->setBarber($barber);
        $appointment->setService($service);
        $appointment->setClient($client);
        $appointment->setClientName($data['client_name'] ?? ($client ? $client->getName() : ''));
        $appointment->setPhone($data['phone'] ?? ($client ? $client->getPhone() : null));
        $appointment->setDate(new \DateTime($data['date'] ?? 'today'));
        $appointment->setTime(new \DateTime($data['time'] ?? 'now'));
        $appointment->setStatus($data['status'] ?? Appointment::STATUS_PENDING);
        $appointment->setPrice($data['price'] ?? $service->getPrice());

        // Validate
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

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_appointments_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointment = $this->appointmentRepository->find($id);

        if (!$appointment || $appointment->getBarber()->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Agendamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read'])
        );
    }

    #[Route('/{id}', name: 'api_appointments_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointment = $this->appointmentRepository->find($id);

        if (!$appointment || $appointment->getBarber()->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Agendamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['barber_id'])) {
            $barber = $this->barberRepository->find($data['barber_id']);
            if (!$barber || $barber->getShop()->getId() !== $shop->getId()) {
                return $this->json(['error' => 'Barbeiro não encontrado'], Response::HTTP_NOT_FOUND);
            }
            $appointment->setBarber($barber);
        }

        if (isset($data['service_id'])) {
            $service = $this->serviceRepository->find($data['service_id']);
            if (!$service || $service->getShop()->getId() !== $shop->getId()) {
                return $this->json(['error' => 'Serviço não encontrado'], Response::HTTP_NOT_FOUND);
            }
            $appointment->setService($service);
        }

        if (isset($data['client_name'])) {
            $appointment->setClientName($data['client_name']);
        }
        if (array_key_exists('phone', $data)) {
            $appointment->setPhone($data['phone']);
        }
        if (isset($data['date'])) {
            $appointment->setDate(new \DateTime($data['date']));
        }
        if (isset($data['time'])) {
            $appointment->setTime(new \DateTime($data['time']));
        }
        if (isset($data['status'])) {
            $appointment->setStatus($data['status']);
        }
        if (isset($data['price'])) {
            $appointment->setPrice($data['price']);
        }

        $existing = $this->appointmentRepository->findOneByBarberAndDateTime(
            $appointment->getBarber(),
            $appointment->getDate(),
            $appointment->getTime(),
            $appointment->getId()
        );
        if ($existing) {
            return $this->json(
                ['error' => 'Este barbeiro já possui um agendamento neste horário. Escolha outro horário ou outro profissional.'],
                Response::HTTP_CONFLICT
            );
        }

        // Validate
        $errors = $this->validator->validate($appointment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read'])
        );
    }

    #[Route('/{id}/confirm', name: 'api_appointments_confirm', methods: ['POST'])]
    public function confirm(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointment = $this->appointmentRepository->find($id);

        if (!$appointment || $appointment->getBarber()->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Agendamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $appointment->confirm();
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read'])
        );
    }

    #[Route('/{id}/complete', name: 'api_appointments_complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointment = $this->appointmentRepository->find($id);

        if (!$appointment || $appointment->getBarber()->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Agendamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $appointment->complete();

        // Update client stats if linked
        $client = $appointment->getClient();
        if ($client) {
            $client->incrementVisits();
            $client->addToTotalSpent($appointment->getPrice());
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read'])
        );
    }

    #[Route('/{id}/cancel', name: 'api_appointments_cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointment = $this->appointmentRepository->find($id);

        if (!$appointment || $appointment->getBarber()->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Agendamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $appointment->cancel();
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read'])
        );
    }

    #[Route('/{id}', name: 'api_appointments_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointment = $this->appointmentRepository->find($id);

        if (!$appointment || $appointment->getBarber()->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Agendamento não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($appointment);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
