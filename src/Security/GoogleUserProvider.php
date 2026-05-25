<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads or creates staff users from Google OAuth (HWI OAuthBundle).
 */
final class GoogleUserProvider implements UserProviderInterface, OAuthAwareUserProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response): UserInterface
    {
        $email = $response->getEmail();
        if ($email === null || $email === '') {
            throw new UserNotFoundException('Google account has no email address.');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_STAFF']);
            $user->setVerified(true);

            $fullName = trim((string) ($response->getRealName() ?: $response->getNickname() ?: ''));
            if ($fullName !== '') {
                $user->setFullName($fullName);
            }

            $user->setPassword($this->passwordHasher->hashPassword(
                $user,
                bin2hex(random_bytes(16)),
            ));

            $this->entityManager->persist($user);
        } else {
            $this->ensureStaffAccess($user);

            if ($user->getFullName() === null || trim((string) $user->getFullName()) === '') {
                $fullName = trim((string) ($response->getRealName() ?: $response->getNickname() ?: ''));
                if ($fullName !== '') {
                    $user->setFullName($fullName);
                }
            }
        }

        $this->entityManager->flush();

        return $user;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        if (!$user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }

    private function ensureStaffAccess(User $user): void
    {
        $roles = $user->getRoles();
        if (!in_array('ROLE_STAFF', $roles, true)) {
            $roles[] = 'ROLE_STAFF';
            $user->setRoles($roles);
        }
        $user->setVerified(true);
    }
}
