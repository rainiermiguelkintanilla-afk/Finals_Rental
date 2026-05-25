<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verify(string $token, EmailVerificationService $verificationService): Response
    {
        $ok = $verificationService->verifyToken($token);

        if ($ok) {
            $this->addFlash('success', 'Email verified successfully. You can now log in.');
        } else {
            $this->addFlash('error', 'Invalid or expired verification link. Please register again if needed.');
        }

        return $this->redirectToRoute('app_login');
    }
}

