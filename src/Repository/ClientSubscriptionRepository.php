<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ClientSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientSubscription>
 */
class ClientSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientSubscription::class);
    }

    public function save(ClientSubscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveForClient(Client $client): ?ClientSubscription
    {
        return $this->findOneBy([
            'client' => $client,
            'status' => ClientSubscription::STATUS_ACTIVE,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * @return ClientSubscription[]
     */
    public function findByClient(Client $client): array
    {
        return $this->findBy(['client' => $client], ['createdAt' => 'DESC']);
    }
}
