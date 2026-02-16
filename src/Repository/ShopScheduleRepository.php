<?php

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\ShopSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopSchedule>
 */
class ShopScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopSchedule::class);
    }

    /**
     * @return ShopSchedule[] Ordenado por dayOfWeek (0 a 6)
     */
    public function findByShopOrderedByDay(Shop $shop): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('s.dayOfWeek', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByShopAndDay(Shop $shop, int $dayOfWeek): ?ShopSchedule
    {
        return $this->findOneBy(['shop' => $shop, 'dayOfWeek' => $dayOfWeek]);
    }
}
