<?php

namespace App\Repository;

use App\Entity\Plan;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plan>
 */
class PlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    public function save(Plan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Plan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Plan[]
     */
    public function findByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop]);
    }

    /**
     * @return Plan[]
     */
    public function findActiveByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop, 'active' => true]);
    }
}
