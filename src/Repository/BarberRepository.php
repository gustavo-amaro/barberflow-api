<?php

namespace App\Repository;

use App\Entity\Barber;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Barber>
 */
class BarberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Barber::class);
    }

    public function save(Barber $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Barber $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Barber[]
     */
    public function findByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop]);
    }

    /**
     * @return Barber[]
     */
    public function findActiveByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop, 'active' => true]);
    }

    /**
     * @return Barber[]
     */
    public function findByShopAndSpecialty(Shop $shop, string $specialty): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.shop = :shop')
            ->andWhere('b.specialty LIKE :specialty')
            ->andWhere('b.active = :active')
            ->setParameter('shop', $shop)
            ->setParameter('specialty', '%' . $specialty . '%')
            ->setParameter('active', true)
            ->orderBy('b.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
