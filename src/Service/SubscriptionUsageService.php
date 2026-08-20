<?php

namespace App\Service;

use App\Entity\ClientSubscription;
use App\Entity\Service;
use App\Entity\SubscriptionUsage;
use App\Entity\User;
use App\Repository\SubscriptionUsageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Regras de negócio de assinaturas de clientes: cálculo de cota restante
 * por ciclo, registro/estorno de uso e renovação/cancelamento de ciclo.
 */
class SubscriptionUsageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubscriptionUsageRepository $usageRepository,
    ) {}

    /**
     * Resumo de cota por serviço do plano, para o ciclo atual da assinatura.
     *
     * @return list<array{service: Service, quantityPerCycle: int, used: int, remaining: int}>
     */
    public function getUsageSummary(ClientSubscription $subscription): array
    {
        $summary = [];
        foreach ($subscription->getPlan()->getItems() as $item) {
            $used = $this->usageRepository->countInCycle($subscription, $item->getService());
            $summary[] = [
                'service' => $item->getService(),
                'quantityPerCycle' => $item->getQuantityPerCycle(),
                'used' => $used,
                'remaining' => max(0, $item->getQuantityPerCycle() - $used),
            ];
        }

        return $summary;
    }

    public function getRemaining(ClientSubscription $subscription, Service $service): int
    {
        foreach ($this->getUsageSummary($subscription) as $entry) {
            if ($entry['service']->getId() === $service->getId()) {
                return $entry['remaining'];
            }
        }

        return 0;
    }

    /**
     * @throws \DomainException se a assinatura não puder consumir esse serviço agora
     */
    public function registerUsage(
        ClientSubscription $subscription,
        Service $service,
        ?User $registeredBy = null,
        ?string $note = null
    ): SubscriptionUsage {
        if (!$subscription->isActive()) {
            throw new \DomainException('Assinatura não está ativa.');
        }

        if ($subscription->getCurrentCycleEnd() < new \DateTimeImmutable()) {
            throw new \DomainException('O ciclo atual da assinatura expirou. Renove antes de registrar novos usos.');
        }

        $planHasService = false;
        foreach ($subscription->getPlan()->getItems() as $item) {
            if ($item->getService()->getId() === $service->getId()) {
                $planHasService = true;
                break;
            }
        }
        if (!$planHasService) {
            throw new \DomainException('Este serviço não faz parte do plano assinado.');
        }

        if ($this->getRemaining($subscription, $service) <= 0) {
            throw new \DomainException('Cota deste serviço já foi totalmente utilizada neste ciclo.');
        }

        $usage = new SubscriptionUsage();
        $usage->setSubscription($subscription);
        $usage->setService($service);
        $usage->setRegisteredBy($registeredBy);
        $usage->setNote($note);

        $this->entityManager->persist($usage);
        $this->entityManager->flush();

        return $usage;
    }

    public function releaseUsage(SubscriptionUsage $usage): void
    {
        $this->entityManager->remove($usage);
        $this->entityManager->flush();
    }

    public function renew(ClientSubscription $subscription, ?string $paymentMethod = null): void
    {
        $now = new \DateTimeImmutable();
        $subscription->setStatus(ClientSubscription::STATUS_ACTIVE);
        $subscription->setCurrentCycleStart($now);
        $subscription->setCurrentCycleEnd($now->modify('+' . $subscription->getPlan()->getCycleDays() . ' days'));
        if ($paymentMethod !== null) {
            $subscription->setPaymentMethod($paymentMethod);
        }

        $this->entityManager->flush();
    }

    public function cancel(ClientSubscription $subscription): void
    {
        $subscription->setStatus(ClientSubscription::STATUS_CANCELLED);
        $subscription->setCancelledAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
