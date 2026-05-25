<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiIndexController extends AbstractController
{
    #[Route('/api', name: 'api_index', methods: ['GET'], priority: 100)]
    public function __invoke(Request $request): JsonResponse
    {
        $userAgent = strtolower((string) $request->headers->get('User-Agent', ''));
        $accept = strtolower((string) $request->headers->get('Accept', ''));

        $looksLikeBrowser = str_contains($userAgent, 'mozilla')
            || str_contains($accept, 'text/html');

        $looksLikeApiClient = str_contains($userAgent, 'thunder client')
            || str_contains($userAgent, 'postmanruntime')
            || str_contains($userAgent, 'insomnia')
            || str_contains($userAgent, 'curl')
            || str_contains($userAgent, 'httpie');

        if ($looksLikeBrowser && !$looksLikeApiClient) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized. This endpoint is intended for API clients (e.g., Thunder Client).',
                'error' => 'unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'message' => 'Rainier Rentals API',
            'data' => [
                'documentation' => '/docs/API.md',
                'mobileApp' => '/mobile/',
                'roles' => [
                    'ROLE_CUSTOMER' => '/api/customer/*',
                    'ROLE_STAFF' => '/api/mobile/*',
                    'ROLE_ADMIN' => 'Web dashboard + staff API',
                ],
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/api/public/apartments', 'auth' => false],
                    ['method' => 'GET', 'path' => '/api/public/apartments/{id}', 'auth' => false],
                    ['method' => 'POST', 'path' => '/api/login', 'auth' => false],
                    ['method' => 'POST', 'path' => '/api/register', 'auth' => false],
                    ['method' => 'POST', 'path' => '/api/auth/google/callback', 'auth' => false],
                    ['method' => 'GET', 'path' => '/api/customer/profile', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'PATCH', 'path' => '/api/customer/profile', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'GET', 'path' => '/api/customer/apartments', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'GET', 'path' => '/api/customer/apartments/{id}', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'GET', 'path' => '/api/customer/leases', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'GET', 'path' => '/api/customer/payments', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'POST', 'path' => '/api/customer/payments/{id}/checkout', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'POST', 'path' => '/api/customer/payments/{id}/sync', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'POST', 'path' => '/api/webhooks/paymongo', 'auth' => false],
                    ['method' => 'GET', 'path' => '/api/customer/bookings', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'POST', 'path' => '/api/customer/bookings', 'auth' => 'ROLE_CUSTOMER'],
                    ['method' => 'GET', 'path' => '/api/mobile/summary', 'auth' => 'ROLE_STAFF'],
                    ['method' => 'GET', 'path' => '/api/mobile/apartments', 'auth' => 'ROLE_STAFF'],
                    ['method' => 'GET', 'path' => '/api/mobile/search?q=', 'auth' => 'ROLE_STAFF'],
                ],
            ],
        ]);
    }
}

