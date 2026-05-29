<?php

namespace App\Repository;

use App\Entity\PushToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushToken>
 */
class PushTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushToken::class);
    }

    /**
     * @return PushToken[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['updatedAt' => 'DESC']);
    }
}
