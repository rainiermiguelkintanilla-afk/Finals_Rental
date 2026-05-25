<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use App\Service\VerificationEmailDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register', name: 'app_register')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EmailVerificationService $emailVerificationService,
        private VerificationEmailDispatcher $verificationEmailDispatcher
    ) {
    }

    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email already exists
            $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', 'An account with this email already exists.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            // Hash password
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            // Set default role to ROLE_STAFF for new registrations
            $user->setRoles(['ROLE_STAFF']);
            $user->setVerified(false);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Queue verification email on async messenger (fast); dev spawns a one-shot consumer.
            $verificationToken = $this->emailVerificationService->createTokenForUser($user);
            $this->verificationEmailDispatcher->dispatch($verificationToken);

            $this->addFlash('success', 'Registration successful! Please check your email to verify your account before signing in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}














