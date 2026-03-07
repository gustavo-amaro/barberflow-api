<?php

namespace App\Repository;

use App\Entity\SubscriptionCharge;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionCharge>
 */
class SubscriptionChargeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionCharge::class);
    }

    public function findPendingByShop(Shop $shop): ?SubscriptionCharge
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.shop = :shop')
            ->andWhere('c.status = :status')
            ->setParameter('shop', $shop)
            ->setParameter('status', SubscriptionCharge::STATUS_PENDING)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByGatewayPaymentId(string $gateway, string $gatewayPaymentId): ?SubscriptionCharge
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.gateway = :gateway')
            ->andWhere('c.gatewayPaymentId = :id')
            ->setParameter('gateway', $gateway)
            ->setParameter('id', $gatewayPaymentId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
