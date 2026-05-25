<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogRepository $activityLogRepository
    ) {
    }

    public function log(
        User $user,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null
    ): void {
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);

        $this->activityLogRepository->save($log, true);
    }

    public function logLogin(User $user): void
    {
        $this->log($user, 'login', null, null, 'User logged in');
    }

    public function logLogout(User $user): void
    {
        $this->log($user, 'logout', null, null, 'User logged out');
    }

    public function logCreate(User $user, string $entityType, int $entityId, ?string $description = null): void
    {
        $this->log($user, 'create', $entityType, $entityId, $description ?? "Created {$entityType} #{$entityId}");
    }

    public function logUpdate(User $user, string $entityType, int $entityId, ?string $description = null): void
    {
        $this->log($user, 'update', $entityType, $entityId, $description ?? "Updated {$entityType} #{$entityId}");
    }

    public function logDelete(User $user, string $entityType, int $entityId, ?string $description = null): void
    {
        $this->log($user, 'delete', $entityType, $entityId, $description ?? "Deleted {$entityType} #{$entityId}");
    }
}
















