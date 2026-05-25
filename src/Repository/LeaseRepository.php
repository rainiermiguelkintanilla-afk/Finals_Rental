<?php

namespace App\Repository;

use App\Entity\Lease;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lease>
 */
class LeaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lease::class);
    }

    public function save(Lease $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Lease $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active leases
     */
    public function findActiveLeases(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.status = :status')
            ->andWhere('l.startDate <= :now')
            ->andWhere('l.endDate >= :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTime())
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find leases by apartment
     */
    public function findByApartment(int $apartmentId): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.apartment = :apartmentId')
            ->setParameter('apartmentId', $apartmentId)
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find leases by tenant
     */
    public function findByTenant(int $tenantId): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.tenant = :tenantId')
            ->setParameter('tenantId', $tenantId)
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expiring leases (within 30 days)
     */
    public function findExpiringLeases(): array
    {
        $thirtyDaysFromNow = new \DateTime('+30 days');
        
        return $this->createQueryBuilder('l')
            ->where('l.status = :status')
            ->andWhere('l.endDate <= :expiryDate')
            ->andWhere('l.endDate >= :now')
            ->setParameter('status', 'active')
            ->setParameter('expiryDate', $thirtyDaysFromNow)
            ->setParameter('now', new \DateTime())
            ->orderBy('l.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}



