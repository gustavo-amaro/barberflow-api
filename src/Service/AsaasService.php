<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\SubscriptionCharge;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AsaasService
{
    /** Parcela única por ciclo: mensal 1x/mês, semestral 1x/6 meses, anual 1x/12 meses */
    private const PLANS = [
        'mensal'     => ['amount' => 40.00, 'description' => 'Link do Barbeiro - Plano Mensal', 'cycle' => 'MONTHLY'],
        'semestral'  => ['amount' => 204.00, 'description' => 'Link do Barbeiro - Plano Semestral', 'cycle' => 'SEMIANNUALLY'],
        'anual'      => ['amount' => 336.00, 'description' => 'Link do Barbeiro - Plano Anual', 'cycle' => 'YEARLY'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private AsaasClientService $asaasClientService
    ) {}

    /**
     * Cria cobrança PIX única para o plano selecionado.
     * Retorna chargeId, encodedImage (QR base64) e payload (copia e cola),
     * no mesmo formato que o front já usa hoje.
     *
     * @param array{plan: string, shopId: int, userId: int, email: string, cpfCnpj: string} $data
     * @return array{chargeId: int, encodedImage: string, payload: string}
     */
    public function createPixCharge(array $data): array
    {
        $plan = $data['plan'] ?? '';
        if (!isset(self::PLANS[$plan])) {
            throw new \InvalidArgumentException('Plano inválido. Use: mensal, semestral ou anual.');
        }

        $shop = $this->em->getRepository(Shop::class)->find($data['shopId'] ?? 0);
        if (!$shop || $shop->getOwner()?->getId() !== (int) ($data['userId'] ?? 0)) {
            throw new \InvalidArgumentException('Barbearia não encontrada ou não pertence ao usuário.');
        }

        $email = $data['email'] ?? $shop->getOwner()?->getEmail() ?? '';
        if ($email === '') {
            throw new \InvalidArgumentException('E-mail é obrigatório.');
        }

        $cpfCnpj = preg_replace('/\D/', '', $data['cpfCnpj'] ?? '');
        if ($cpfCnpj === '') {
            throw new \InvalidArgumentException('Para criar esta cobrança é necessário preencher o CPF ou CNPJ do cliente.');
        }

        $planConfig = self::PLANS[$plan];
        $amount = (float) $planConfig['amount'];

        $charge = new SubscriptionCharge();
        $charge->setUser($shop->getOwner());
        $charge->setShop($shop);
        $charge->setPlan($plan);
        $charge->setAmount((string) $amount);
        $charge->setGateway(SubscriptionCharge::GATEWAY_ASAAS);
        $charge->setStatus(SubscriptionCharge::STATUS_PENDING);

        $this->em->persist($charge);
        $this->em->flush();

        // Para PIX usamos sempre um cliente ASAAS vinculado ao CPF/CNPJ informado.
        // Isso garante que mesmo se existir um asaasCustomerId antigo sem documento,
        // vamos buscar/criar um cliente correto com CPF/CNPJ.
        $clientData = $this->asaasClientService->findOrCreateClient([
            'name'    => $shop->getOwner()?->getName() ?? $shop->getName() ?? 'Cliente',
            'cpfCnpj' => $cpfCnpj,
            'email'   => $email,
        ]);
        $customerId = $clientData['id'] ?? null;
        if (!$customerId) {
            throw new \RuntimeException('Resposta inválida ao criar/obter cliente no ASAAS');
        }

        $shop->setAsaasCustomerId($customerId);
        $this->em->flush();

        $baseURL = $this->asaasClientService->getBaseURL();

        $paymentBody = [
            'customer'          => $customerId,
            'billingType'       => 'PIX',
            'value'             => $amount,
            'dueDate'           => (new \DateTimeImmutable())->format('Y-m-d'),
            'description'       => $planConfig['description'],
            'externalReference' => (string) $charge->getId(),
        ];

        $paymentResponse = $this->httpClient->request('POST', $baseURL . 'payments', [
            'timeout' => 30,
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
                'access_token' => $this->asaasClientService->getAccessToken(),
            ],
            'body' => json_encode($paymentBody),
        ]);

        if (!in_array($paymentResponse->getStatusCode(), [Response::HTTP_OK, Response::HTTP_CREATED], true)) {
            $err = $paymentResponse->toArray(false);
            $charge->setStatus(SubscriptionCharge::STATUS_FAILED);
            $this->em->flush();

            $msg = $err['errors'][0]['description'] ?? 'Erro ao criar cobrança PIX no ASAAS';
            throw new \RuntimeException($msg, $paymentResponse->getStatusCode());
        }

        $payment = $paymentResponse->toArray(false);
        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            $charge->setStatus(SubscriptionCharge::STATUS_FAILED);
            $this->em->flush();
            throw new \RuntimeException('Resposta inválida ao criar cobrança PIX no ASAAS');
        }

        $charge->setGatewayPaymentId((string) $paymentId);
        $this->em->flush();

        // Busca QRCode PIX (imagem base64 + payload copia e cola)
        $qrResponse = $this->httpClient->request('GET', $baseURL . 'payments/' . $paymentId . '/pixQrCode', [
            'timeout' => 30,
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
                'access_token' => $this->asaasClientService->getAccessToken(),
            ],
        ]);

        if ($qrResponse->getStatusCode() !== Response::HTTP_OK) {
            $err = $qrResponse->toArray(false);
            $msg = $err['errors'][0]['description'] ?? 'Erro ao gerar QR Code PIX no ASAAS';
            throw new \RuntimeException($msg, $qrResponse->getStatusCode());
        }

        $qr = $qrResponse->toArray(false);

        return [
            'chargeId'     => $charge->getId(),
            'encodedImage' => $qr['encodedImage'] ?? '',
            'payload'      => $qr['payload'] ?? '',
        ];
    }

    /**
     * Consulta status da cobrança PIX (para polling no front).
     */
    public function getChargeStatus(int $chargeId, int $userId): ?array
    {
        $charge = $this->em->getRepository(SubscriptionCharge::class)->find($chargeId);
        if (!$charge || $charge->getUser()?->getId() !== $userId) {
            return null;
        }

        return [
            'chargeId' => $charge->getId(),
            'status'   => $charge->getStatus(),
        ];
    }

    /**
     * Cria assinatura recorrente com cartão no ASAAS e ativa o plano na Shop.
     * Requer: plan, shop, user, dados do cartão e do titular, remoteIp.
     *
     * @param array{
     *   plan: string,
     *   shopId: int,
     *   userId: int,
     *   name: string,
     *   email: string,
     *   cpfCnpj: string,
     *   postalCode?: string,
     *   addressNumber?: string,
     *   phone?: string,
     *   addressComplement?: string,
     *   mobilePhone?: string,
     *   cardNumber: string,
     *   cardHolderName: string,
     *   cardExpiryMonth: string,
     *   cardExpiryYear: string,
     *   cardCvv: string,
     *   remoteIp: string
     * } $data
     * @return array{subscriptionId: string}
     */
    public function createSubscriptionCreditCard(array $data): array
    {
        $plan = $data['plan'] ?? '';
        if (!isset(self::PLANS[$plan])) {
            throw new \InvalidArgumentException('Plano inválido. Use: mensal, semestral ou anual.');
        }

        $shop = $this->em->getRepository(Shop::class)->find($data['shopId'] ?? 0);
        if (!$shop || $shop->getOwner()?->getId() !== (int) ($data['userId'] ?? 0)) {
            throw new \InvalidArgumentException('Barbearia não encontrada ou não pertence ao usuário.');
        }

        $oldSubscriptionId = $shop->getAsaasSubscriptionId();
        if ($oldSubscriptionId !== null && $oldSubscriptionId !== '') {
            $this->cancelSubscriptionInAsaas($oldSubscriptionId);
            $shop->setAsaasSubscriptionId(null);
            $shop->setAsaasCustomerId(null);
            $this->em->flush();
        }

        $planConfig = self::PLANS[$plan];
        $amount = (float) $planConfig['amount'];

        $postalCode = preg_replace('/\D/', '', $data['postalCode'] ?? '');
        if (strlen($postalCode) !== 8) {
            throw new \InvalidArgumentException('CEP é obrigatório e deve conter 8 dígitos.');
        }

        $clientData = $this->asaasClientService->findOrCreateClient([
            'name'    => $data['name'] ?? 'Cliente',
            'cpfCnpj' => $data['cpfCnpj'],
            'email'   => $data['email'] ?? null,
        ]);

        $customerId = $clientData['id'];

        $expiryMonth = str_pad((string) $data['cardExpiryMonth'], 2, '0', STR_PAD_LEFT);
        $expiryYear  = (string) $data['cardExpiryYear'];
        if (strlen($expiryYear) === 2) {
            $expiryYear = '20' . $expiryYear;
        }

        $body = [
            'customer'     => $customerId,
            'billingType'  => 'CREDIT_CARD',
            'value'        => $amount,
            'nextDueDate'  => (new \DateTimeImmutable())->format('Y-m-d'),
            'cycle'        => $planConfig['cycle'],
            'description'  => $planConfig['description'],
            'externalReference' => (string) $shop->getId(),
            'creditCard'   => [
                'holderName'  => $data['cardHolderName'],
                'number'      => preg_replace('/\D/', '', $data['cardNumber']),
                'expiryMonth' => $expiryMonth,
                'expiryYear'  => $expiryYear,
                'ccv'         => $data['cardCvv'],
            ],
            'creditCardHolderInfo' => [
                'name'               => $data['name'] ?? $data['cardHolderName'],
                'email'              => $data['email'],
                'cpfCnpj'            => preg_replace('/\D/', '', $data['cpfCnpj']),
                'postalCode'         => $postalCode,
                'addressNumber'      => $data['addressNumber'] ?? 'S/N',
                'addressComplement'  => $data['addressComplement'] ?? null,
                'phone'              => preg_replace('/\D/', '', $data['phone'] ?? '') ?: '00000000000',
                'mobilePhone'        => isset($data['mobilePhone']) && $data['mobilePhone'] !== '' ? preg_replace('/\D/', '', $data['mobilePhone']) : null,
            ],
            'remoteIp' => $data['remoteIp'],
        ];

        $baseURL = $this->asaasClientService->getBaseURL();
        $response = $this->httpClient->request('POST', $baseURL . 'subscriptions', [
            'timeout'  => 65,
            'body'     => json_encode($body),
            'headers'  => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
                'access_token' => $this->asaasClientService->getAccessToken(),
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $err = $response->toArray(false);
            $msg = $err['errors'][0]['description'] ?? 'Erro ao criar assinatura no ASAAS';
            throw new \RuntimeException($msg, $response->getStatusCode());
        }

        $result = $response->toArray();
        $subscriptionId = $result['id'] ?? '';

        if ($subscriptionId === '') {
            throw new \RuntimeException('Resposta inválida do ASAAS');
        }

        $endsAt = $this->computeSubscriptionEndsAt($plan, $shop->getSubscriptionEndsAt());
        $shop->setSubscriptionPlan($plan);
        $shop->setSubscriptionEndsAt($endsAt);
        $shop->setAsaasSubscriptionId($subscriptionId);
        $shop->setAsaasCustomerId($customerId);
        $this->em->flush();

        $charge = new SubscriptionCharge();
        $charge->setUser($shop->getOwner());
        $charge->setShop($shop);
        $charge->setPlan($plan);
        $charge->setAmount((string) $amount);
        $charge->setGateway(SubscriptionCharge::GATEWAY_ASAAS);
        $charge->setStatus(SubscriptionCharge::STATUS_PAID);
        $charge->setGatewayPaymentId($subscriptionId);
        $charge->setPaidAt(new \DateTimeImmutable());
        $charge->setPaymentData($result);
        $this->em->persist($charge);
        $this->em->flush();

        return ['subscriptionId' => $subscriptionId];
    }

    /**
     * Processa webhook PAYMENT_RECEIVED do ASAAS.
     * - Se houver subscription: renova assinatura recorrente (cartão).
     * - Se não houver subscription mas houver externalReference numérico: finaliza cobrança PIX única.
     */
    public function handlePaymentReceivedWebhook(array $payload): bool
    {
        if (($payload['event'] ?? '') !== 'PAYMENT_RECEIVED') {
            return false;
        }

        $payment = $payload['payment'] ?? [];
        $subscriptionId = $payment['subscription'] ?? null;

        if ($subscriptionId !== null && $subscriptionId !== '') {
            $shop = $this->em->getRepository(Shop::class)->findOneBy(['asaasSubscriptionId' => $subscriptionId]);
            if (!$shop) {
                return false;
            }

            $plan = $shop->getSubscriptionPlan();
            if ($plan === null || $plan === '') {
                return false;
            }

            $endsAt = $this->computeSubscriptionEndsAt($plan, $shop->getSubscriptionEndsAt());
            $shop->setSubscriptionEndsAt($endsAt);
            $this->em->flush();

            $amount = $payment['value'] ?? 0;
            $charge = new SubscriptionCharge();
            $charge->setUser($shop->getOwner());
            $charge->setShop($shop);
            $charge->setPlan($plan);
            $charge->setAmount((string) $amount);
            $charge->setGateway(SubscriptionCharge::GATEWAY_ASAAS);
            $charge->setStatus(SubscriptionCharge::STATUS_PAID);
            $charge->setGatewayPaymentId((string) ($payment['id'] ?? ''));
            $charge->setPaidAt(new \DateTimeImmutable());
            $charge->setPaymentData($payment);
            $this->em->persist($charge);
            $this->em->flush();

            return true;
        }

        $externalRef = $payment['externalReference'] ?? null;
        if ($externalRef === null || $externalRef === '') {
            return false;
        }

        $charge = $this->em->getRepository(SubscriptionCharge::class)->find((int) $externalRef);
        if (!$charge || $charge->getStatus() !== SubscriptionCharge::STATUS_PENDING) {
            return false;
        }

        $shop = $charge->getShop();
        $plan = $charge->getPlan();
        if ($shop === null || $plan === null || $plan === '') {
            return false;
        }

        $charge->setStatus(SubscriptionCharge::STATUS_PAID);
        $charge->setPaidAt(new \DateTimeImmutable());
        $charge->setPaymentData($payment);

        $endsAt = $this->computeSubscriptionEndsAt($plan, $shop->getSubscriptionEndsAt());
        $shop->setSubscriptionPlan($plan);
        $shop->setSubscriptionEndsAt($endsAt);

        $this->em->flush();

        return true;
    }

    /**
     * Cancela assinatura recorrente no ASAAS. Remove cobranças futuras.
     * O usuário mantém acesso até subscriptionEndsAt.
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        $shop = $this->em->getRepository(Shop::class)->findOneBy(['asaasSubscriptionId' => $subscriptionId]);
        if (!$shop) {
            throw new \InvalidArgumentException('Assinatura não encontrada.');
        }

        $this->cancelSubscriptionInAsaas($subscriptionId);
        $shop->setAsaasSubscriptionId(null);
        $shop->setAsaasCustomerId(null);
        $this->em->flush();

        return true;
    }

    /**
     * Chama o ASAAS para remover a assinatura (só a API, não altera a Shop).
     * Se a assinatura já não existir (404), ignora para não falhar troca de plano.
     */
    private function cancelSubscriptionInAsaas(string $subscriptionId): void
    {
        $baseURL = $this->asaasClientService->getBaseURL();
        $response = $this->httpClient->request('DELETE', $baseURL . 'subscriptions/' . $subscriptionId, [
            'timeout' => 15,
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
                'access_token' => $this->asaasClientService->getAccessToken(),
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status === Response::HTTP_OK) {
            return;
        }
        if ($status === Response::HTTP_NOT_FOUND) {
            return;
        }
        $err = $response->toArray(false);
        $msg = $err['errors'][0]['description'] ?? 'Erro ao cancelar assinatura no ASAAS';
        throw new \RuntimeException($msg, $status);
    }

    private function computeSubscriptionEndsAt(string $plan, ?\DateTimeImmutable $currentEndsAt): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $from = $currentEndsAt && $currentEndsAt > $now ? $currentEndsAt : $now;

        return match ($plan) {
            'mensal'    => $from->modify('+1 month'),
            'semestral' => $from->modify('+6 months'),
            'anual'     => $from->modify('+12 months'),
            default     => $from->modify('+1 month'),
        };
    }
}
