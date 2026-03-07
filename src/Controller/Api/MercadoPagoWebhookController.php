<?php

namespace App\Controller\Api;

use App\Service\MercadoPagoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api/mercadopago')]
class MercadoPagoWebhookController extends AbstractController
{
    public function __construct(
        private MercadoPagoService $mercadoPagoService
    ) {
    }

    #[Route('/webhook', name: 'api_mercadopago_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $ok = $this->mercadoPagoService->finishChargeByWebhook($payload);
            return $this->json(['success' => $ok], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
