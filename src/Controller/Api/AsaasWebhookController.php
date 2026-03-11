<?php

namespace App\Controller\Api;

use App\Service\AsaasService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/asaas')]
class AsaasWebhookController extends AbstractController
{
    public function __construct(
        private AsaasService $asaasService,
        private ?string $webhookToken = null
    ) {
    }

    #[Route('/webhook/payment', name: 'api_asaas_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        if ($this->webhookToken !== null && $this->webhookToken !== '') {
            $headerToken = $request->headers->get('asaas-access-token');
            if ($headerToken !== $this->webhookToken) {
                return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $ok = $this->asaasService->handlePaymentReceivedWebhook($payload);
            return $this->json(['success' => $ok], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
