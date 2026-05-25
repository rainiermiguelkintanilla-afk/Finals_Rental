<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(ActivityLogRepository $activityLogRepository, Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Get filter parameters
        $userId = $request->query->get('user');
        $action = $request->query->get('action');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        // Build query with filters
        $qb = $activityLogRepository->createQueryBuilder('a');

        if ($userId) {
            $qb->andWhere('a.user = :userId')
               ->setParameter('userId', $userId);
        }

        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }

        if ($dateFrom) {
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('a.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $logs = $qb->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Count total with same filters
        $countQb = $activityLogRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($userId) {
            $countQb->andWhere('a.user = :userId')
                     ->setParameter('userId', $userId);
        }

        if ($action) {
            $countQb->andWhere('a.action = :action')
                     ->setParameter('action', $action);
        }

        if ($dateFrom) {
            $countQb->andWhere('a.createdAt >= :dateFrom')
                    ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo) {
            $countQb->andWhere('a.createdAt <= :dateTo')
                    ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $totalLogs = $countQb->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalLogs / $limit);

        // Get all users for filter dropdown
        $userRepository = $this->entityManager->getRepository(\App\Entity\User::class);
        $users = $userRepository->findAll();

        return $this->render('dashboard/activity_log/index.html.twig', [
            'logs' => $logs,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_logs' => $totalLogs,
            'users' => $users,
            'filters' => [
                'user' => $userId,
                'action' => $action,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
