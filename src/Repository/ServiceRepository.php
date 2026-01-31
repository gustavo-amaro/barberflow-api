<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function save(Service $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Service $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Service[]
     */
    public function findByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop]);
    }

    /**
     * @return Service[]
     */
    public function findActiveByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop, 'active' => true]);
    }

    /**
     * @return Service[]
     */
    public function findPopularByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop, 'active' => true, 'popular' => true]);
    }

    /**
     * @return Service[]
     */
    public function findByShopAndCategory(Shop $shop, string $category): array
    {
        return $this->findBy(['shop' => $shop, 'category' => $category, 'active' => true]);
    }
}
