<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StaffGoogleController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    private function getRedirectUri(Request $request): string
    {
        // Staff flow must use /staff/google/callback — not the HWI path in GOOGLE_REDIRECT_URI.
        return $this->urlGenerator->generate(
            'app_staff_google_callback',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    #[Route('/staff/google', name: 'app_staff_google_start', methods: ['GET'])]
    public function start(Request $request): RedirectResponse
    {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

        if ($clientId === '' || $clientSecret === '') {
            throw $this->createNotFoundException('Google OAuth is not configured.');
        }

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('google_oauth_state', $state);

        $redirectUri = $this->getRedirectUri($request);

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
        return new RedirectResponse($url);
    }

    #[Route('/staff/google/callback', name: 'app_staff_google_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

        if ($clientId === '' || $clientSecret === '') {
            throw $this->createNotFoundException('Google OAuth is not configured.');
        }

        $code = (string) $request->query->get('code', '');
        $state = (string) $request->query->get('state', '');

        if ($code === '') {
            $this->addFlash('error', 'Google login failed: missing authorization code.');
            return $this->redirectToRoute('app_login');
        }

        $expectedState = (string) $request->getSession()->get('google_oauth_state', '');
        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            $this->addFlash('error', 'Google login failed: invalid state.');
            return $this->redirectToRoute('app_login');
        }

        $request->getSession()->remove('google_oauth_state');

        $redirectUri = $this->getRedirectUri($request);

        // Exchange code for access token.
        $tokenResponse = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query([
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]),
        ])->toArray(false);

        $accessToken = (string) ($tokenResponse['access_token'] ?? '');
        if ($accessToken === '') {
            $this->addFlash('error', 'Google login failed: missing access token.');
            return $this->redirectToRoute('app_login');
        }

        // Fetch Google user profile.
        $profile = $this->httpClient->request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ])->toArray(false);

        $email = (string) ($profile['email'] ?? '');
        $emailVerified = (bool) ($profile['email_verified'] ?? false);

        if ($email === '' || !$emailVerified) {
            $this->addFlash('error', 'Google did not verify your email address.');
            return $this->redirectToRoute('app_login');
        }

        /** @var User|null $user */
        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNewUser = false;

        if (!$user) {
            $user = new User();
            $isNewUser = true;
            $user->setEmail($email);
            $user->setRoles(['ROLE_STAFF']);
            $user->setVerified(true);

            // Password is required by the entity mapping; generate a random one.
            $randomPassword = bin2hex(random_bytes(16));
            $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

            $this->entityManager->persist($user);
        } else {
            $roles = $user->getRoles();
            if (!in_array('ROLE_STAFF', $roles, true)) {
                $roles[] = 'ROLE_STAFF';
                $user->setRoles($roles);
            }
            $user->setVerified(true);
        }

        $this->entityManager->flush();

        // Log the staff user into the existing `main` firewall.
        $response = $this->security->login($user, null, 'main');

        // If login already returned a response, keep it.
        if ($response instanceof Response) {
            return $response;
        }

        return $this->redirectToRoute('app_dashboard_index');
    }
}

