<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Client;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\BarberRepository;
use App\Repository\ClientRepository;
use App\Repository\ServiceRepository;
use App\Repository\ShopRepository;
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
        private ValidatorInterface $validator
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
            ],
            'services' => $this->serializer->normalize($services, null, ['groups' => 'service:read']),
            'barbers' => $this->serializer->normalize($barbers, null, ['groups' => 'barber:read']),
        ]);
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
        $existing = $this->appointmentRepository->findOneByBarberAndDateTime($barber, $aptDate, $aptTime);
        if ($existing) {
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
        $appointment->setStatus($data['status'] ?? Appointment::STATUS_PENDING);
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

        return $this->json(
            $this->serializer->normalize($appointment, null, ['groups' => 'appointment:read']),
            Response::HTTP_CREATED
        );
    }
}
