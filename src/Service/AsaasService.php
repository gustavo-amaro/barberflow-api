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

        $baseURL = $this->asaasClientService->getBaseURL();
        $response = $this->httpClient->request('DELETE', $baseURL . 'subscriptions/' . $subscriptionId, [
            'timeout' => 15,
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
                'access_token' => $this->asaasClientService->getAccessToken(),
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $err = $response->toArray(false);
            $msg = $err['errors'][0]['description'] ?? 'Erro ao cancelar assinatura no ASAAS';
            throw new \RuntimeException($msg, $response->getStatusCode());
        }

        $shop->setAsaasSubscriptionId(null);
        $shop->setAsaasCustomerId(null);
        $this->em->flush();

        return true;
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
