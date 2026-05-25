<?php

namespace App\Service;

use App\Entity\Tenant;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class CustomerContext
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getUser(): User
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if (!$user->isCustomer()) {
            throw new AccessDeniedHttpException('Customer account required.');
        }

        return $user;
    }

    public function getTenant(): Tenant
    {
        $user = $this->getUser();
        $tenant = $user->getTenant();
        if ($tenant === null) {
            throw new NotFoundHttpException('No tenant profile linked to this account.');
        }

        return $tenant;
    }
}
