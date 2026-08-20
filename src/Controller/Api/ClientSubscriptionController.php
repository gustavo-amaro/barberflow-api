<?php

namespace App\Controller\Api;

use App\Entity\ClientSubscription;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\ClientSubscriptionRepository;
use App\Repository\PlanRepository;
use App\Repository\ServiceRepository;
use App\Repository\SubscriptionUsageRepository;
use App\Service\SubscriptionUsageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ClientSubscriptionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientRepository $clientRepository,
        private PlanRepository $planRepository,
        private ServiceRepository $serviceRepository,
        private ClientSubscriptionRepository $subscriptionRepository,
        private SubscriptionUsageRepository $usageRepository,
        private SubscriptionUsageService $usageService,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/api/clients/{clientId}/subscription', name: 'api_client_subscription_show', methods: ['GET'])]
    public function show(int $clientId): JsonResponse
    {
        [$shop, $client, $error] = $this->resolveClient($clientId);
        if ($error) {
            return $error;
        }

        $subscription = $this->subscriptionRepository->findActiveForClient($client);

        return $this->json($this->serializeSubscription($subscription));
    }

    #[Route('/api/clients/{clientId}/subscriptions', name: 'api_client_subscription_create', methods: ['POST'])]
    public function subscribe(int $clientId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        [$shop, $client, $error] = $this->resolveClient($clientId);
        if ($error) {
            return $error;
        }

        if ($this->subscriptionRepository->findActiveForClient($client)) {
            return $this->json(
                ['error' => 'Cliente já possui uma assinatura ativa. Cancele-a antes de assinar um novo plano.'],
                Response::HTTP_CONFLICT
            );
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        $plan = $this->planRepository->find($data['planId'] ?? 0);
        if (!$plan || $plan->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Plano não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $now = new \DateTimeImmutable();

        $subscription = new ClientSubscription();
        $subscription->setClient($client);
        $subscription->setPlan($plan);
        $subscription->setStatus(ClientSubscription::STATUS_ACTIVE);
        $subscription->setStartedAt($now);
        $subscription->setCurrentCycleStart($now);
        $subscription->setCurrentCycleEnd($now->modify('+' . $plan->getCycleDays() . ' days'));
        $subscription->setPaymentMethod($data['paymentMethod'] ?? null);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $this->json($this->serializeSubscription($subscription), Response::HTTP_CREATED);
    }

    #[Route('/api/client-subscriptions/{id}/renew', name: 'api_client_subscription_renew', methods: ['POST'])]
    public function renew(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        [$subscription, $error] = $this->resolveSubscription($id);
        if ($error) {
            return $error;
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $this->usageService->renew($subscription, $data['paymentMethod'] ?? null);

        return $this->json($this->serializeSubscription($subscription));
    }

    #[Route('/api/client-subscriptions/{id}/cancel', name: 'api_client_subscription_cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        [$subscription, $error] = $this->resolveSubscription($id);
        if ($error) {
            return $error;
        }

        $this->usageService->cancel($subscription);

        return $this->json($this->serializeSubscription($subscription));
    }

    #[Route('/api/client-subscriptions/{id}/usage', name: 'api_client_subscription_usage_index', methods: ['GET'])]
    public function usageHistory(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        [$subscription, $error] = $this->resolveSubscription($id);
        if ($error) {
            return $error;
        }

        $usages = $this->usageRepository->findBySubscription($subscription);

        return $this->json(
            $this->serializer->normalize($usages, null, ['groups' => 'subscription_usage:read'])
        );
    }

    #[Route('/api/client-subscriptions/{id}/usage', name: 'api_client_subscription_usage_create', methods: ['POST'])]
    public function registerUsage(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        /** @var User $user */
        $user = $this->getUser();
        [$subscription, $error] = $this->resolveSubscription($id);
        if ($error) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        $service = $this->serviceRepository->find($data['serviceId'] ?? 0);
        if (!$service || $service->getShop()->getId() !== $subscription->getPlan()->getShop()->getId()) {
            return $this->json(['error' => 'Serviço não encontrado'], Response::HTTP_NOT_FOUND);
        }

        try {
            $usage = $this->usageService->registerUsage($subscription, $service, $user, $data['note'] ?? null);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'usage' => $this->serializer->normalize($usage, null, ['groups' => 'subscription_usage:read']),
            'subscription' => $this->serializeSubscription($subscription),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/client-subscriptions/{id}/usage/{usageId}', name: 'api_client_subscription_usage_delete', methods: ['DELETE'])]
    public function releaseUsage(int $id, int $usageId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');
        [$subscription, $error] = $this->resolveSubscription($id);
        if ($error) {
            return $error;
        }

        $usage = $this->usageRepository->find($usageId);
        if (!$usage || $usage->getSubscription()->getId() !== $subscription->getId()) {
            return $this->json(['error' => 'Registro de uso não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->usageService->releaseUsage($usage);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{0: \App\Entity\Shop|null, 1: \App\Entity\Client|null, 2: JsonResponse|null}
     */
    private function resolveClient(int $clientId): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return [null, null, $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND)];
        }

        $client = $this->clientRepository->find($clientId);
        if (!$client || $client->getShop()->getId() !== $shop->getId()) {
            return [null, null, $this->json(['error' => 'Cliente não encontrado'], Response::HTTP_NOT_FOUND)];
        }

        return [$shop, $client, null];
    }

    /**
     * @return array{0: ClientSubscription|null, 1: JsonResponse|null}
     */
    private function resolveSubscription(int $id): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return [null, $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND)];
        }

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription || $subscription->getClient()->getShop()->getId() !== $shop->getId()) {
            return [null, $this->json(['error' => 'Assinatura não encontrada'], Response::HTTP_NOT_FOUND)];
        }

        return [$subscription, null];
    }

    private function serializeSubscription(?ClientSubscription $subscription): ?array
    {
        if (!$subscription) {
            return null;
        }

        $data = $this->serializer->normalize($subscription, null, ['groups' => 'client_subscription:read']);
        $data['usageSummary'] = array_map(
            fn (array $entry) => [
                'service' => $this->serializer->normalize($entry['service'], null, ['groups' => 'plan:read']),
                'quantityPerCycle' => $entry['quantityPerCycle'],
                'used' => $entry['used'],
                'remaining' => $entry['remaining'],
            ],
            $this->usageService->getUsageSummary($subscription)
        );

        return $data;
    }
}
