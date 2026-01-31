<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Product[]
     */
    public function findByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop]);
    }

    /**
     * @return Product[]
     */
    public function findActiveByShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop, 'active' => true]);
    }

    /**
     * @return Product[]
     */
    public function findLowStockByShop(Shop $shop): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.shop = :shop')
            ->andWhere('p.active = :active')
            ->andWhere('p.minStock IS NOT NULL')
            ->andWhere('p.stock <= p.minStock')
            ->setParameter('shop', $shop)
            ->setParameter('active', true)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Product[]
     */
    public function findByShopAndCategory(Shop $shop, string $category): array
    {
        return $this->findBy(['shop' => $shop, 'category' => $category, 'active' => true]);
    }
}
