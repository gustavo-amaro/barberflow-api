<?php

namespace App\Repository;

use App\Entity\ClientSubscription;
use App\Entity\Service;
use App\Entity\SubscriptionUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionUsage>
 */
class SubscriptionUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionUsage::class);
    }

    public function save(SubscriptionUsage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SubscriptionUsage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countInCycle(ClientSubscription $subscription, Service $service): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.subscription = :subscription')
            ->andWhere('u.service = :service')
            ->andWhere('u.createdAt BETWEEN :start AND :end')
            ->setParameter('subscription', $subscription)
            ->setParameter('service', $service)
            ->setParameter('start', $subscription->getCurrentCycleStart())
            ->setParameter('end', $subscription->getCurrentCycleEnd())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return SubscriptionUsage[]
     */
    public function findBySubscription(ClientSubscription $subscription): array
    {
        return $this->findBy(['subscription' => $subscription], ['createdAt' => 'DESC']);
    }
}
