<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use App\Service\EmailVerificationService;
use App\Service\VerificationEmailDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiRegisterController extends AbstractController
{
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EmailVerificationService $emailVerificationService,
        VerificationEmailDispatcher $verificationEmailDispatcher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return ApiResponseFactory::error('Invalid JSON body.', 'invalid_json');
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $fullName = trim((string) ($data['fullName'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $accountType = strtolower(trim((string) ($data['accountType'] ?? 'customer')));

        $validationErrors = [];
        if ($email === '') {
            $validationErrors['email'] = 'Email is required.';
        }
        if ($password === '') {
            $validationErrors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $validationErrors['password'] = 'Password must be at least 8 characters.';
        }

        if ($validationErrors !== []) {
            return ApiResponseFactory::error(
                'Validation failed.',
                'validation_failed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validationErrors,
            );
        }

        $existingUser = $userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            return ApiResponseFactory::error(
                'An account with this email already exists.',
                'email_already_used',
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User();
        $user->setEmail($email);

        if ($accountType === 'staff') {
            $user->setRoles(['ROLE_STAFF']);
            $user->setVerified(false);
        } else {
            $user->setRoles(['ROLE_CUSTOMER']);
            $user->setVerified(true);

            if ($fullName !== '') {
                $user->setFullName($fullName);
            }

            $tenant = $this->createTenantFromRegistration($email, $fullName, $phone);
            $entityManager->persist($tenant);
            $user->setTenant($tenant);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        if (!$user->isVerified()) {
            $verificationToken = $emailVerificationService->createTokenForUser($user);
            $verificationEmailDispatcher->dispatch($verificationToken);
        }

        $message = $user->isVerified()
            ? 'Registration successful. You can log in with the mobile app.'
            : 'Registration successful. Please verify your email to log in. Check your inbox for the verification link.';

        return ApiResponseFactory::success([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'tenantId' => $user->getTenant()?->getId(),
        ], $message, Response::HTTP_CREATED);
    }

    private function createTenantFromRegistration(string $email, string $fullName, string $phone): Tenant
    {
        $firstName = 'Customer';
        $lastName = 'User';

        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, 2) ?: [];
            $firstName = $parts[0] ?? $firstName;
            $lastName = $parts[1] ?? $lastName;
        } else {
            $local = strstr($email, '@', true) ?: 'customer';
            $firstName = ucfirst($local);
        }

        $tenant = new Tenant();
        $tenant->setFirstName($firstName);
        $tenant->setLastName($lastName);
        $tenant->setEmail($email);
        $tenant->setPhone($phone !== '' ? $phone : 'N/A');
        $tenant->setMoveInDate(new \DateTime());
        $tenant->setStatus('active');

        return $tenant;
    }
}
