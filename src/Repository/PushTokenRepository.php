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

    public function findOneByToken(string $token): ?PushToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * @return list<PushToken>
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['updatedAt' => 'DESC']);
    }

    /**
     * @return list<string>
     */
    public function findTokensForUser(User $user): array
    {
        $tokens = [];
        foreach ($this->findByUser($user) as $row) {
            if ($row->getToken() !== null && $row->getToken() !== '') {
                $tokens[] = $row->getToken();
            }
        }

        return $tokens;
    }
}
