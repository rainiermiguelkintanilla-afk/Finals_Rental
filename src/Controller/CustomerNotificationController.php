<?php

namespace App\Controller;

use App\Entity\PushToken;
use App\Repository\PushTokenRepository;
use App\Service\ApiResponseFactory;
use App\Service\CustomerContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/customer')]
#[IsGranted('ROLE_CUSTOMER')]
final class CustomerNotificationController extends AbstractController
{
    public function __construct(
        private readonly CustomerContext $customerContext,
    ) {
    }

    #[Route('/push-token', name: 'api_customer_push_token_register', methods: ['POST'])]
    public function registerPushToken(
        Request $request,
        PushTokenRepository $pushTokens,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return ApiResponseFactory::error('Invalid JSON body.', 'invalid_json');
        }

        $token = trim((string) ($data['token'] ?? ''));
        if ($token === '' || !str_starts_with($token, 'ExponentPushToken[')) {
            return ApiResponseFactory::error('Invalid Expo push token.', 'invalid_token');
        }

        $user = $this->customerContext->getUser();
        $platform = isset($data['platform']) ? substr((string) $data['platform'], 0, 32) : null;

        $existing = $pushTokens->findOneByToken($token);
        if ($existing === null) {
            $existing = new PushToken();
            $existing->setToken($token);
        }

        $existing->setUser($user);
        $existing->setPlatform($platform);
        $existing->touch();
        $entityManager->persist($existing);
        $entityManager->flush();

        return ApiResponseFactory::success(['registered' => true], 'Push token saved.');
    }

    #[Route('/push-token', name: 'api_customer_push_token_delete', methods: ['DELETE'])]
    public function deletePushToken(
        Request $request,
        PushTokenRepository $pushTokens,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $token = is_array($data) ? trim((string) ($data['token'] ?? '')) : '';

        if ($token === '') {
            return ApiResponseFactory::error('Token is required.', 'invalid_token');
        }

        $row = $pushTokens->findOneByToken($token);
        if ($row !== null && $row->getUser()?->getId() === $this->customerContext->getUser()->getId()) {
            $entityManager->remove($row);
            $entityManager->flush();
        }

        return ApiResponseFactory::success(['deleted' => true], 'Push token removed.');
    }

    #[Route('/notification-preferences', name: 'api_customer_notification_preferences', methods: ['GET', 'PATCH'])]
    public function notificationPreferences(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->customerContext->getUser();

        if ($request->isMethod('PATCH')) {
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                return ApiResponseFactory::error('Invalid JSON body.', 'invalid_json');
            }

            if (array_key_exists('notifyEmail', $data)) {
                $user->setNotifyEmail((bool) $data['notifyEmail']);
            }
            if (array_key_exists('notifyPush', $data)) {
                $user->setNotifyPush((bool) $data['notifyPush']);
            }
            if (array_key_exists('notifyPaymentReminders', $data)) {
                $user->setNotifyPaymentReminders((bool) $data['notifyPaymentReminders']);
            }
            if (array_key_exists('notifyMaintenance', $data)) {
                $user->setNotifyMaintenance((bool) $data['notifyMaintenance']);
            }

            $entityManager->flush();
        }

        return ApiResponseFactory::success([
            'notifyEmail' => $user->isNotifyEmail(),
            'notifyPush' => $user->isNotifyPush(),
            'notifyPaymentReminders' => $user->isNotifyPaymentReminders(),
            'notifyMaintenance' => $user->isNotifyMaintenance(),
        ], 'Notification preferences loaded.');
    }
}
