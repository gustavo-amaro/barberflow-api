<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\SubscriptionCharge;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AsaasService
{
    /** Valor por parcela mensal; semestral = 6x, anual = 12x */
    private const PLANS = [
        'mensal'     => ['amount' => 40.00, 'description' => 'Link do Barbeiro - Plano Mensal', 'cycle' => 'MONTHLY', 'maxPayments' => null],
        'semestral'  => ['amount' => 34.00, 'description' => 'Link do Barbeiro - Plano Semestral (6x)', 'cycle' => 'MONTHLY', 'maxPayments' => 6],
        'anual'      => ['amount' => 28.00, 'description' => 'Link do Barbeiro - Plano Anual (12x)', 'cycle' => 'MONTHLY', 'maxPayments' => 12],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private AsaasClientService $asaasClientService
    ) {
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

        $planConfig = self::PLANS[$plan];
        $amount = (float) $planConfig['amount'];

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
        ];
        if (isset($planConfig['maxPayments']) && $planConfig['maxPayments'] !== null) {
            $body['maxPayments'] = $planConfig['maxPayments'];
        }
        $body = array_merge($body, [
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
                'postalCode'         => preg_replace('/\D/', '', $data['postalCode'] ?? '') ?: '00000000',
                'addressNumber'      => $data['addressNumber'] ?? 'S/N',
                'addressComplement'  => $data['addressComplement'] ?? null,
                'phone'              => preg_replace('/\D/', '', $data['phone'] ?? '') ?: '00000000000',
                'mobilePhone'        => isset($data['mobilePhone']) && $data['mobilePhone'] !== '' ? preg_replace('/\D/', '', $data['mobilePhone']) : null,
            ],
            'remoteIp' => $data['remoteIp'],
        ]);

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
     * Processa webhook PAYMENT_RECEIVED do ASAAS para renovação de assinatura.
     * Estende subscriptionEndsAt da Shop quando o pagamento é de uma assinatura nossa.
     */
    public function handlePaymentReceivedWebhook(array $payload): bool
    {
        if (($payload['event'] ?? '') !== 'PAYMENT_RECEIVED') {
            return false;
        }

        $payment = $payload['payment'] ?? [];
        $subscriptionId = $payment['subscription'] ?? null;
        if ($subscriptionId === null || $subscriptionId === '') {
            return false;
        }

        $shop = $this->em->getRepository(Shop::class)->findOneBy(['asaasSubscriptionId' => $subscriptionId]);
        if (!$shop) {
            return false;
        }

        $plan = $shop->getSubscriptionPlan();
        if ($plan === null || $plan === '') {
            return false;
        }

        // Cada pagamento = 1 mês (todos os planos cobram em parcelas mensais)
        $endsAt = $this->computeSubscriptionEndsAtForPayment($shop->getSubscriptionEndsAt());
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

    /** Data de fim ao criar a assinatura (primeira cobrança já cobre 1 mês). */
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

    /** A cada pagamento recebido (webhook), estende 1 mês (cobrança mensal). */
    private function computeSubscriptionEndsAtForPayment(?\DateTimeImmutable $currentEndsAt): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $from = $currentEndsAt && $currentEndsAt > $now ? $currentEndsAt : $now;

        return $from->modify('+1 month');
    }
}
