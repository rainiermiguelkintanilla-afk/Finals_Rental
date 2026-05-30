<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Payment>
     */
    public function findDueWithinDays(int $days): array
    {
        $today = new \DateTimeImmutable('today');
        $until = $today->modify(sprintf('+%d days', $days));

        return $this->createQueryBuilder('p')
            ->where('p.status != :paid')
            ->andWhere('p.dueDate >= :today')
            ->andWhere('p.dueDate <= :until')
            ->setParameter('paid', 'paid')
            ->setParameter('today', $today)
            ->setParameter('until', $until)
            ->getQuery()
            ->getResult();
    }
}
