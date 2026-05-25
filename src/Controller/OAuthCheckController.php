<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Placeholder route so /connect/google/check resolves in the router.
 * HWI OAuthBundle authenticates this request in the firewall before the controller runs.
 */
final class OAuthCheckController extends AbstractController
{
    #[Route('/connect/google/check', name: 'oauth_google_check', methods: ['GET'])]
    public function googleCheck(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
