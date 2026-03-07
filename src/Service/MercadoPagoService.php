<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\SubscriptionCharge;
use App\Repository\SubscriptionChargeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;

class MercadoPagoService
{
    private const PLANS = [
        'mensal' => ['amount' => 40.00, 'description' => 'Link do Barbeiro - Plano Mensal'],
        'semestral' => ['amount' => 204.00, 'description' => 'Link do Barbeiro - Plano Semestral'],
        'anual' => ['amount' => 336.00, 'description' => 'Link do Barbeiro - Plano Anual'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private SubscriptionChargeRepository $chargeRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    private function getAccessToken(): string
    {
        $env = $_ENV['APP_ENV'] ?? 'dev';
        $token = $env === 'prod'
            ? ($_ENV['MERCADOPAGO_PROD_ACCESS_TOKEN'] ?? '')
            : ($_ENV['MERCADOPAGO_SANDBOX_ACCESS_TOKEN'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Mercado Pago access token não configurado (MERCADOPAGO_SANDBOX_ACCESS_TOKEN ou MERCADOPAGO_PROD_ACCESS_TOKEN)');
        }
        return $token;
    }

    private function getBaseUrl(): string
    {
        return $_ENV['MERCADOPAGO_BASE_URL'] ?? 'https://api.mercadopago.com/v1';
    }

    /**
     * Cria cobrança PIX para assinatura. Persiste SubscriptionCharge e chama API MP.
     * Retorna chargeId, qr_code_base64, qr_code (copia e cola).
     *
     * @param array{plan: string, shopId: int, userId: int, email: string} $data
     */
    public function createPixCharge(array $data): array
    {
        $plan = $data['plan'] ?? '';
        if (!isset(self::PLANS[$plan])) {
            throw new \InvalidArgumentException('Plano inválido. Use: mensal, semestral ou anual.');
        }

        $shop = $this->em->getRepository(Shop::class)->find($data['shopId'] ?? 0);
        if (!$shop || $shop->getOwner()?->getId() !== (int) ($data['userId'] ?? 0)) {
            throw new \InvalidArgumentException('Shop não encontrado ou não pertence ao usuário.');
        }

        $email = $data['email'] ?? $shop->getOwner()?->getEmail() ?? '';
        if ($email === '') {
            throw new \InvalidArgumentException('E-mail é obrigatório.');
        }

        $planConfig = self::PLANS[$plan];
        $amount = (float) $planConfig['amount'];

        $charge = new SubscriptionCharge();
        $charge->setUser($shop->getOwner());
        $charge->setShop($shop);
        $charge->setPlan($plan);
        $charge->setAmount((string) $amount);
        $charge->setGateway(SubscriptionCharge::GATEWAY_MERCADOPAGO);
        $charge->setStatus(SubscriptionCharge::STATUS_PENDING);

        $this->em->persist($charge);
        $this->em->flush();

        $payload = [
            'transaction_amount' => $amount,
            'description' => $planConfig['description'],
            'payment_method_id' => 'pix',
            'external_reference' => (string) $charge->getId(),
            'notification_url' => $this->urlGenerator->generate('api_mercadopago_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'payer' => [
                'email' => $email,
                'first_name' => $shop->getOwner()?->getName() ?? 'Cliente',
            ],
        ];

        $baseUrl = rtrim($this->getBaseUrl(), '/');
        $response = $this->httpClient->request('POST', $baseUrl . '/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => 'subscription-' . $charge->getId(),
            ],
            'json' => $payload,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            $errors = $response->toArray(false);
            $charge->setStatus(SubscriptionCharge::STATUS_FAILED);
            $this->em->flush();

            $message = $errors['message'] ?? $errors['error'] ?? 'Erro ao gerar pagamento PIX';
            $fullText = is_string($message) ? $message : json_encode($errors);

            // Resposta genérica do MP (ex.: uso de chave pública em vez de token)
            if (stripos($fullText, 'developers.mercadopago') !== false || stripos($fullText, 'MercadoLibre') !== false) {
                $message = 'Use o Access Token (token de acesso secreto), não a Chave pública. Em Suas integrações > Credenciais, copie o "Access Token" de teste ou produção.';
            }

            throw new \RuntimeException($message, $response->getStatusCode());
        }

        $body = $response->toArray(false);
        $charge->setGatewayPaymentId((string) ($body['id'] ?? ''));
        $this->em->flush();

        $pointOfInteraction = $body['point_of_interaction'] ?? [];
        $transactionData = $pointOfInteraction['transaction_data'] ?? [];
        $qrCode = $transactionData['qr_code'] ?? '';
        $qrCodeBase64 = $transactionData['qr_code_base64'] ?? '';

        return [
            'chargeId' => $charge->getId(),
            'encodedImage' => $qrCodeBase64,
            'payload' => $qrCode,
        ];
    }

    /**
     * Consulta status da cobrança (para polling no front).
     */
    public function getChargeStatus(int $chargeId, int $userId): ?array
    {
        $charge = $this->em->getRepository(SubscriptionCharge::class)->find($chargeId);
        if (!$charge || $charge->getUser()?->getId() !== $userId) {
            return null;
        }

        return [
            'chargeId' => $charge->getId(),
            'status' => $charge->getStatus(),
        ];
    }

    /**
     * Busca pagamento no MP e, se aprovado, finaliza a cobrança e ativa a assinatura na Shop.
     * Chamado pelo webhook.
     */
    public function finishChargeByWebhook(array $payload): bool
    {
        if (($payload['type'] ?? '') !== 'payment') {
            return false;
        }
        $paymentId = $payload['data']['id'] ?? null;
        if ($paymentId === null) {
            return false;
        }

        $details = $this->getPaymentFromMp((string) $paymentId);
        if (!$details || ($details['status'] ?? '') !== 'approved' || ($details['payment_method_id'] ?? '') !== 'pix') {
            return false;
        }

        $externalRef = $details['external_reference'] ?? null;
        if ($externalRef === null || $externalRef === '') {
            return false;
        }

        $charge = $this->em->getRepository(SubscriptionCharge::class)->find((int) $externalRef);
        if (!$charge || $charge->getStatus() !== SubscriptionCharge::STATUS_PENDING) {
            return false;
        }

        $charge->setStatus(SubscriptionCharge::STATUS_PAID);
        $charge->setPaidAt(new \DateTimeImmutable());
        $charge->setPaymentData($details);

        $shop = $charge->getShop();
        $plan = $charge->getPlan();

        $endsAt = $this->computeSubscriptionEndsAt($plan, $shop->getSubscriptionEndsAt());
        $shop->setSubscriptionPlan($plan);
        $shop->setSubscriptionEndsAt($endsAt);

        $this->em->flush();
        return true;
    }

    private function getPaymentFromMp(string $paymentId): ?array
    {
        try {
            $baseUrl = rtrim($this->getBaseUrl(), '/');
            $response = $this->httpClient->request('GET', $baseUrl . '/payments/' . $paymentId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            return $response->toArray(false);
        } catch (\Throwable) {
            return null;
        }
    }

    private function computeSubscriptionEndsAt(string $plan, ?\DateTimeImmutable $currentEndsAt): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $from = $currentEndsAt && $currentEndsAt > $now ? $currentEndsAt : $now;

        return match ($plan) {
            'mensal' => $from->modify('+1 month'),
            'semestral' => $from->modify('+6 months'),
            'anual' => $from->modify('+12 months'),
            default => $from->modify('+1 month'),
        };
    }
}
