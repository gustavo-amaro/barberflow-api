<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Client[]
     */
    public function findByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop]);
    }

    /**
     * @return Client[]
     */
    public function findFavoritesByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop, 'favorite' => true]);
    }

    public function findByShopAndPhone(Shop $shop, string $phone): ?Client
    {
        return $this->findOneBy(['shop' => $shop, 'phone' => $phone]);
    }

    /**
     * @return Client[]
     */
    public function findTopClientsByShop(Shop $shop, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('c.totalSpent', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Client[]
     */
    public function searchByNameOrPhone(Shop $shop, string $search): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.shop = :shop')
            ->andWhere('c.name LIKE :search OR c.phone LIKE :search')
            ->setParameter('shop', $shop)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
