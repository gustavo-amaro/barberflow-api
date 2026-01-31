<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Barber;
use App\Entity\Client;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function save(Appointment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Appointment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Appointment[]
     */
    public function findByBarber(Barber $barber): array
    {
        return $this->findBy(['barber' => $barber], ['date' => 'DESC', 'time' => 'DESC']);
    }

    /**
     * @return Appointment[]
     */
    public function findByClient(Client $client): array
    {
        return $this->findBy(['client' => $client], ['date' => 'DESC', 'time' => 'DESC']);
    }

    /**
     * @return Appointment[]
     */
    public function findByBarberAndDate(Barber $barber, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.barber = :barber')
            ->andWhere('a.date = :date')
            ->andWhere('a.status != :cancelled')
            ->setParameter('barber', $barber)
            ->setParameter('date', $date)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
            ->orderBy('a.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Appointment[]
     */
    public function findByShopAndDate(Shop $shop, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.barber', 'b')
            ->andWhere('b.shop = :shop')
            ->andWhere('a.date = :date')
            ->setParameter('shop', $shop)
            ->setParameter('date', $date)
            ->orderBy('a.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Appointment[]
     */
    public function findByShopAndDateRange(Shop $shop, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.barber', 'b')
            ->andWhere('b.shop = :shop')
            ->andWhere('a.date >= :startDate')
            ->andWhere('a.date <= :endDate')
            ->setParameter('shop', $shop)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.date', 'ASC')
            ->addOrderBy('a.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Appointment[]
     */
    public function findPendingByShop(Shop $shop): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.barber', 'b')
            ->andWhere('b.shop = :shop')
            ->andWhere('a.status = :status')
            ->setParameter('shop', $shop)
            ->setParameter('status', Appointment::STATUS_PENDING)
            ->orderBy('a.date', 'ASC')
            ->addOrderBy('a.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Appointment[]
     */
    public function findTodayByShop(Shop $shop): array
    {
        $today = new \DateTime('today');

        return $this->findByShopAndDate($shop, $today);
    }

    public function countByShopAndStatus(Shop $shop, string $status): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.barber', 'b')
            ->andWhere('b.shop = :shop')
            ->andWhere('a.status = :status')
            ->setParameter('shop', $shop)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalRevenueByShop(Shop $shop, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): string
    {
        $qb = $this->createQueryBuilder('a')
            ->select('SUM(a.price)')
            ->join('a.barber', 'b')
            ->andWhere('b.shop = :shop')
            ->andWhere('a.status = :status')
            ->setParameter('shop', $shop)
            ->setParameter('status', Appointment::STATUS_COMPLETED);

        if ($startDate) {
            $qb->andWhere('a.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('a.date <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getSingleScalarResult() ?? '0.00';
    }
}
