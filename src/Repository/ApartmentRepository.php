<?php

namespace App\Repository;

use App\Entity\Apartment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Apartment>
 */
class ApartmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Apartment::class);
    }

    public function save(Apartment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Apartment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Apartment>
     */
    public function findAvailableForPublic(int $limit = 6): array
    {
        try {
            return $this->findBy(['status' => 'available'], ['id' => 'DESC'], $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<Apartment>
     */
    public function findForPublicApi(?string $status = null): array
    {
        try {
            $criteria = [];
            if (is_string($status) && $status !== '' && $status !== 'all') {
                $criteria['status'] = $status;
            }

            return $this->findBy($criteria, ['id' => 'DESC']);
        } catch (\Throwable) {
            return [];
        }
    }
}
