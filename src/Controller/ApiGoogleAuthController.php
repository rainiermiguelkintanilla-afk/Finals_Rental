<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mobile Google Sign-In: client sends Google tokens; we verify with Google and return a JWT.
 */
#[Route('/api/auth/google')]
class ApiGoogleAuthController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/callback', name: 'api_auth_google_callback', methods: ['POST'])]
    public function callback(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return ApiResponseFactory::error('Invalid JSON body.', 'invalid_json');
        }

        $accessToken = trim((string) ($data['token'] ?? $data['access_token'] ?? ''));
        $idToken = trim((string) ($data['id_token'] ?? ''));

        if ($accessToken === '' && $idToken === '') {
            return ApiResponseFactory::error(
                'Google access token or id_token is required.',
                'missing_token',
            );
        }

        $profile = null;
        if ($idToken !== '') {
            $profile = $this->fetchProfileFromIdToken($idToken);
        }
        if ($profile === null && $accessToken !== '') {
            $profile = $this->fetchProfileFromAccessToken($accessToken);
        }

        if ($profile === null) {
            return ApiResponseFactory::error(
                'Failed to fetch user data from Google.',
                'google_profile_failed',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $email = trim((string) ($profile['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ApiResponseFactory::error(
                'A valid Google email is required.',
                'invalid_email',
            );
        }

        if (!$this->isEmailVerified($profile['email_verified'] ?? false)) {
            return ApiResponseFactory::error(
                'Google did not verify your email address.',
                'email_not_verified',
            );
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = $this->createCustomerFromGoogle($email, $profile);
        } else {
            $denied = $this->prepareExistingUser($user, $profile);
            if ($denied !== null) {
                return $denied;
            }
        }

        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return ApiResponseFactory::success([
            'token' => $token,
            'user' => $this->serializeAuthUser($user, $profile),
        ], 'Google sign-in successful.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileFromAccessToken(string $accessToken): ?array
    {
        try {
            $profile = $this->httpClient->request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ])->toArray(false);

            return is_array($profile) && isset($profile['email']) ? $profile : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileFromIdToken(string $idToken): ?array
    {
        try {
            $profile = $this->httpClient->request(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo',
                [
                    'query' => [
                        'id_token' => $idToken,
                    ],
                ],
            )->toArray(false);

            if (!is_array($profile) || !isset($profile['email'])) {
                return null;
            }

            if (!isset($profile['name']) && (isset($profile['given_name']) || isset($profile['family_name']))) {
                $profile['name'] = trim(
                    ((string) ($profile['given_name'] ?? '')).' '.((string) ($profile['family_name'] ?? '')),
                );
            }

            return $profile;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isEmailVerified(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function createCustomerFromGoogle(string $email, array $profile): User
    {
        $fullName = trim((string) ($profile['name'] ?? ''));

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_CUSTOMER']);
        $user->setVerified(true);

        if ($fullName !== '') {
            $user->setFullName($fullName);
        }

        $user->setPassword($this->passwordHasher->hashPassword(
            $user,
            bin2hex(random_bytes(16)),
        ));

        $tenant = $this->createTenantFromGoogleProfile($email, $fullName);
        $this->entityManager->persist($tenant);
        $user->setTenant($tenant);
        $this->entityManager->persist($user);

        return $user;
    }

    /**
     * Upgrades or validates an existing account for mobile customer Google sign-in.
     *
     * @param array<string, mixed> $profile
     */
    private function prepareExistingUser(User $user, array $profile): ?JsonResponse
    {
        if (($user->isAdmin() || $user->isStaff()) && !$user->isCustomer()) {
            return ApiResponseFactory::error(
                'This Google account is registered for staff access. Use the staff web portal instead.',
                'staff_account',
                Response::HTTP_FORBIDDEN,
            );
        }

        $user->setVerified(true);

        if (!$user->isCustomer()) {
            $storedRoles = array_values(array_filter(
                $user->getRoles(),
                static fn (string $role): bool => $role !== 'ROLE_USER',
            ));
            if (!in_array('ROLE_CUSTOMER', $storedRoles, true)) {
                $storedRoles[] = 'ROLE_CUSTOMER';
            }
            $user->setRoles($storedRoles);
        }

        if ($user->getFullName() === null || trim((string) $user->getFullName()) === '') {
            $fullName = trim((string) ($profile['name'] ?? ''));
            if ($fullName !== '') {
                $user->setFullName($fullName);
            }
        }

        if ($user->isCustomer() && $user->getTenant() === null) {
            $tenant = $this->createTenantFromGoogleProfile(
                (string) $user->getEmail(),
                (string) ($user->getFullName() ?? ''),
            );
            $this->entityManager->persist($tenant);
            $user->setTenant($tenant);
        }

        return null;
    }

    private function createTenantFromGoogleProfile(string $email, string $fullName): Tenant
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
        $tenant->setPhone('N/A');
        $tenant->setMoveInDate(new \DateTime());
        $tenant->setStatus('active');

        return $tenant;
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed>
     */
    private function serializeAuthUser(User $user, array $profile): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'name' => $user->getDisplayName(),
            'picture' => $profile['picture'] ?? null,
            'roles' => $user->getRoles(),
            'tenantId' => $user->getTenant()?->getId(),
        ];
    }
}
