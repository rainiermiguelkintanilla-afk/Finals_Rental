<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiLoginController extends AbstractController
{
    private const API_VERIFY_RESEND_COOLDOWN_SECONDS = 90;

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        EmailVerificationService $emailVerificationService,
        #[Autowire(service: 'cache.app')]
        CacheItemPoolInterface $cache,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON body.',
                'error' => 'invalid_json',
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json([
                'success' => false,
                'message' => 'Email and password are required.',
                'error' => 'missing_credentials',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid credentials.',
                'error' => 'invalid_credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isCustomer()) {
            return $this->json([
                'success' => false,
                'message' => 'This login is for customer accounts only. Staff should use the web dashboard at /login.',
                'error' => 'customer_account_required',
            ], Response::HTTP_FORBIDDEN);
        }

        if (method_exists($user, 'isVerified') && !$user->isVerified()) {
            $cacheKey = 'api_verify_resend_'.md5(strtolower((string) $user->getEmail()));
            $item = $cache->getItem($cacheKey);
            if (!$item->isHit()) {
                $emailVerificationService->queueFreshVerificationEmail($user);
                $item->set(true);
                $item->expiresAfter(self::API_VERIFY_RESEND_COOLDOWN_SECONDS);
                $cache->save($item);
                $message = 'Please verify your email before logging in. A new verification link has been sent to your inbox.';
            } else {
                $message = 'Please verify your email before logging in. If you need another link, wait a minute and try again.';
            }

            return $this->json([
                'success' => false,
                'message' => $message,
                'error' => 'email_not_verified',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $jwtManager->create($user);

        return $this->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                    'displayName' => $user->getDisplayName(),
                    'roles' => $user->getRoles(),
                    'tenantId' => $user->getTenant()?->getId(),
                ],
            ],
        ]);
    }
}

