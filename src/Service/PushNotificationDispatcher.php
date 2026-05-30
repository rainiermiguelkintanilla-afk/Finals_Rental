<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\LeaseRepository;
use App\Repository\PushTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maps realtime business events to Expo push + email alerts.
 */
final class PushNotificationDispatcher
{
    public function __construct(
        private readonly ExpoPushService $expoPush,
        private readonly NotificationEmailService $email,
        private readonly PushTokenRepository $pushTokens,
        private readonly UserRepository $users,
        private readonly LeaseRepository $leases,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $type, array $payload = []): void
    {
        match ($type) {
            'booking.updated' => $this->notifyUserById(
                isset($payload['userId']) ? (int) $payload['userId'] : 0,
                'Booking updated',
                sprintf('Your booking status is now: %s', (string) ($payload['status'] ?? 'updated')),
                ['screen' => 'bookings', 'event' => $type],
            ),
            'booking.deleted' => $this->notifyUserById(
                isset($payload['userId']) ? (int) $payload['userId'] : 0,
                'Booking cancelled',
                'Your booking request was cancelled.',
                ['screen' => 'bookings', 'event' => $type],
            ),
            'booking.created' => $this->handleBookingCreated($payload),
            'payment.paid' => $this->handlePaymentPaid($payload),
            'apartment.updated' => $this->handleApartmentUpdated($payload),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleBookingCreated(array $payload): void
    {
        $userId = isset($payload['userId']) ? (int) $payload['userId'] : 0;
        $status = (string) ($payload['status'] ?? 'pending');
        $apartment = (string) ($payload['apartment'] ?? 'an apartment');

        if ($userId > 0) {
            $this->notifyUserById(
                $userId,
                'Booking submitted',
                sprintf('We received your booking request for %s.', $apartment),
                ['screen' => 'bookings', 'event' => 'booking.created'],
            );
        }

        $this->notifyStaff(
            'New booking request',
            sprintf('New booking for %s (status: %s).', $apartment, $status),
            ['screen' => 'rentals', 'event' => 'booking.created'],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handlePaymentPaid(array $payload): void
    {
        $tenantId = isset($payload['tenantId']) ? (int) $payload['tenantId'] : 0;
        if ($tenantId <= 0) {
            return;
        }

        $amount = $payload['amount'] ?? '';
        $apartment = (string) ($payload['apartment'] ?? 'your unit');
        $user = $this->users->findOneByTenantId($tenantId);
        if (!$user instanceof User) {
            return;
        }

        $this->notifyUser(
            $user,
            'Payment confirmed',
            sprintf('Payment of ₱%s for %s was received.', $amount, $apartment),
            ['screen' => 'payments', 'event' => 'payment.paid'],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleApartmentUpdated(array $payload): void
    {
        $status = strtolower((string) ($payload['status'] ?? ''));
        if ($status !== 'maintenance') {
            return;
        }

        $apartmentId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $name = (string) ($payload['name'] ?? 'Your building');
        if ($apartmentId <= 0) {
            return;
        }

        foreach ($this->leases->findActiveByApartment($apartmentId) as $lease) {
            $tenant = $lease->getTenant();
            if ($tenant === null) {
                continue;
            }
            $user = $this->users->findOneByTenantId((int) $tenant->getId());
            if (!$user instanceof User || !$user->isNotifyMaintenance()) {
                continue;
            }
            $this->notifyUser(
                $user,
                'Maintenance alert',
                sprintf('%s is scheduled for maintenance. Our team will contact you if needed.', $name),
                ['screen' => 'profile', 'event' => 'apartment.maintenance'],
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        if ($user->isNotifyPush()) {
            $tokens = $this->pushTokens->findTokensForUser($user);
            $this->expoPush->sendToTokens($tokens, $title, $body, $data);
        }

        $this->email->sendAlert(
            $user,
            $title,
            '<p>'.htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notifyUserById(int $userId, string $title, string $body, array $data = []): void
    {
        if ($userId <= 0) {
            return;
        }

        $user = $this->users->find($userId);
        if (!$user instanceof User) {
            return;
        }

        $this->notifyUser($user, $title, $body, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notifyStaff(string $title, string $body, array $data = []): void
    {
        $staff = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.roles LIKE :staff OR u.roles LIKE :admin')
            ->setParameter('staff', '%ROLE_STAFF%')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();

        foreach ($staff as $user) {
            if ($user instanceof User) {
                $this->notifyUser($user, $title, $body, $data);
            }
        }
    }
}
