<?php

namespace App\Controller\Api;

use App\Entity\ClientSubscription;
use App\Entity\Plan;
use App\Entity\PlanService;
use App\Entity\User;
use App\Repository\ClientSubscriptionRepository;
use App\Repository\PlanRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/plans')]
class PlanController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlanRepository $planRepository,
        private ServiceRepository $serviceRepository,
        private ClientSubscriptionRepository $clientSubscriptionRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_plans_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $active = $request->query->get('active');
        $plans = $active === 'true'
            ? $this->planRepository->findActiveByShop($shop)
            : $this->planRepository->findByShop($shop);

        return $this->json(
            $this->serializer->normalize($plans, null, ['groups' => 'plan:read'])
        );
    }

    #[Route('', name: 'api_plans_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        $plan = new Plan();
        $plan->setShop($shop);
        $plan->setName($data['name'] ?? '');
        $plan->setPrice($data['price'] ?? '0.00');
        $plan->setCycleDays($data['cycleDays'] ?? 30);
        $plan->setNotes($data['notes'] ?? null);
        $plan->setActive($data['active'] ?? true);

        $itemsError = $this->applyItems($plan, $data['services'] ?? [], $shop);
        if ($itemsError) {
            return $itemsError;
        }

        $errors = $this->validator->validate($plan);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($plan);
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($plan, null, ['groups' => 'plan:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_plans_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $plan = $this->planRepository->find($id);

        if (!$plan || $plan->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Plano não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($plan, null, ['groups' => 'plan:read'])
        );
    }

    #[Route('/{id}', name: 'api_plans_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $plan = $this->planRepository->find($id);

        if (!$plan || $plan->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Plano não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $plan->setName($data['name']);
        }
        if (isset($data['price'])) {
            $plan->setPrice($data['price']);
        }
        if (isset($data['cycleDays'])) {
            $plan->setCycleDays($data['cycleDays']);
        }
        if (array_key_exists('notes', $data)) {
            $plan->setNotes($data['notes']);
        }
        if (isset($data['active'])) {
            $plan->setActive($data['active']);
        }

        if (array_key_exists('services', $data)) {
            $itemsError = $this->applyItems($plan, $data['services'] ?? [], $shop, true);
            if ($itemsError) {
                return $itemsError;
            }
        }

        $errors = $this->validator->validate($plan);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($plan, null, ['groups' => 'plan:read'])
        );
    }

    #[Route('/{id}', name: 'api_plans_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $plan = $this->planRepository->find($id);

        if (!$plan || $plan->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Plano não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $activeSubscriptions = $this->clientSubscriptionRepository->findBy([
            'plan' => $plan,
            'status' => ClientSubscription::STATUS_ACTIVE,
        ]);
        if (count($activeSubscriptions) > 0) {
            return $this->json(
                ['error' => 'Não é possível excluir: existem clientes com assinatura ativa neste plano.'],
                Response::HTTP_CONFLICT
            );
        }

        $this->entityManager->remove($plan);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<int, array{serviceId?: int, quantityPerCycle?: int}> $items
     */
    private function applyItems(Plan $plan, array $items, \App\Entity\Shop $shop, bool $replacing = false): ?JsonResponse
    {
        if ($replacing) {
            $plan->clearItems();
        }

        foreach ($items as $itemData) {
            $service = $this->serviceRepository->find($itemData['serviceId'] ?? 0);
            if (!$service || $service->getShop()->getId() !== $shop->getId()) {
                return $this->json(['error' => 'Serviço inválido em "services"'], Response::HTTP_BAD_REQUEST);
            }

            $quantity = (int) ($itemData['quantityPerCycle'] ?? 0);
            if ($quantity < 1) {
                return $this->json(['error' => 'quantityPerCycle deve ser maior que zero'], Response::HTTP_BAD_REQUEST);
            }

            $planService = new PlanService();
            $planService->setService($service);
            $planService->setQuantityPerCycle($quantity);
            $plan->addItem($planService);
        }

        return null;
    }
}
