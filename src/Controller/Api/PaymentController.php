<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AsaasService;
use App\Service\MercadoPagoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/payment')]
class PaymentController extends AbstractController
{
    public function __construct(
        private MercadoPagoService $mercadoPagoService,
        private AsaasService $asaasService
    ) {
    }

    /**
     * Gera cobrança PIX para o plano selecionado. Requer autenticação.
     * Body: { "plan": "mensal" | "semestral" | "anual" }
     */
    #[Route('/pix', name: 'api_payment_pix', methods: ['POST'])]
    public function createPix(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Não autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Nenhuma barbearia vinculada'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $plan = $data['plan'] ?? '';

        try {
            $result = $this->mercadoPagoService->createPixCharge([
                'plan' => $plan,
                'shopId' => $shop->getId(),
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
            ]);
            return $this->json($result, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() && \is_int($e->getCode()) ? $e->getCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
            return $this->json(['error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erro ao gerar PIX'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Retorna status da cobrança (pending, paid, failed, expired). Para polling no front.
     */
    #[Route('/pix/{id}/status', name: 'api_payment_pix_status', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pixStatus(int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Não autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $status = $this->mercadoPagoService->getChargeStatus($id, $user->getId());
        if ($status === null) {
            return $this->json(['error' => 'Cobrança não encontrada'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($status);
    }

    /**
     * Cria assinatura recorrente com cartão (ASAAS). Cobrança automática a cada ciclo.
     * Body: plan, name, email, cpfCnpj, cardNumber, cardHolderName, cardExpiryMonth, cardExpiryYear, cardCvv.
     * Endereço e telefone são opcionais (usa valores padrão se não informados).
     */
    #[Route('/credit-card', name: 'api_payment_credit_card', methods: ['POST'])]
    public function createCreditCardSubscription(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Não autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $shop = $user->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Nenhuma barbearia vinculada'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $data['shopId'] = $shop->getId();
        $data['userId'] = $user->getId();
        $data['name'] = $data['name'] ?? $user->getName();
        $data['email'] = $data['email'] ?? $user->getEmail();
        $data['remoteIp'] = $request->server->get('REMOTE_ADDR') ?? '127.0.0.1';

        try {
            $result = $this->asaasService->createSubscriptionCreditCard($data);
            return $this->json($result, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() && \is_int($e->getCode()) ? $e->getCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
            return $this->json(['error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erro ao processar cartão de crédito.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
