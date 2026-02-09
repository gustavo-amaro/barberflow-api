<?php

namespace App\Repository;

use App\Entity\ProductMovement;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductMovement>
 */
class ProductMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductMovement::class);
    }

    /**
     * Número de vendas (registros de saída) no mês atual da loja.
     * Cada registro de venda conta como 1, independente da quantidade.
     */
    public function countSalesThisMonth(Shop $shop): int
    {
        $start = new \DateTimeImmutable('first day of this month 00:00:00');
        $end = new \DateTimeImmutable('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.shop = :shop')
            ->andWhere('m.operation = :operation')
            ->andWhere('m.createdAt >= :start')
            ->andWhere('m.createdAt <= :end')
            ->setParameter('shop', $shop)
            ->setParameter('operation', ProductMovement::OPERATION_SALE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Receita de vendas (quantidade * preço do produto) no período.
     */
    public function getSalesRevenueByShopAndPeriod(Shop $shop, \DateTimeInterface $start, \DateTimeInterface $end): string
    {
        $result = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.quantity * p.price), 0)')
            ->join('m.product', 'p')
            ->andWhere('m.shop = :shop')
            ->andWhere('m.operation = :operation')
            ->andWhere('m.createdAt >= :start')
            ->andWhere('m.createdAt <= :end')
            ->setParameter('shop', $shop)
            ->setParameter('operation', ProductMovement::OPERATION_SALE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
        return (string) $result;
    }
}
