<?php

namespace App\Service;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmailVerificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly MailerInterface $mailer,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly VerificationEmailDispatcher $verificationEmailDispatcher,
        #[Autowire('%env(BREVO_API_KEY)%')]
        private readonly string $brevoApiKey,
        #[Autowire('%env(BREVO_SENDER_EMAIL)%')]
        private readonly string $brevoSenderEmail,
        #[Autowire('%env(BREVO_SENDER_NAME)%')]
        private readonly string $brevoSenderName,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
        #[Autowire('%env(APP_PUBLIC_URL)%')]
        private readonly string $publicBaseUrl,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri
    ) {
    }

    /**
     * Invalidate unused tokens, create a new one, and queue the verification email (async transport).
     */
    public function queueFreshVerificationEmail(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(EmailVerificationToken::class, 't')
            ->where('t.user = :user')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        $token = $this->createTokenForUser($user);
        $this->verificationEmailDispatcher->dispatch($token);
    }

    public function createTokenForUser(User $user, int $ttlHours = 24): EmailVerificationToken
    {
        $token = bin2hex(random_bytes(32));

        $emailToken = new EmailVerificationToken();
        $emailToken->setUser($user);
        $emailToken->setToken($token);
        $emailToken->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d hours', $ttlHours)));

        $this->entityManager->persist($emailToken);
        $this->entityManager->flush();

        return $emailToken;
    }

    public function sendVerificationEmail(User $user, EmailVerificationToken $token): bool
    {
        $verifyUrl = $this->buildVerifyUrl($token->getToken());

        // If a Brevo *API* key isn't configured (or an SMTP key was pasted),
        // fall back to Symfony Mailer (configure MAILER_DSN to Brevo SMTP).
        if ($this->brevoApiKey === '' || str_starts_with($this->brevoApiKey, 'xsmtpsib-')) {
            return $this->sendVerificationEmailTemplated($user, $verifyUrl);
        }

        $subject = 'Verify your email address';
        $html = sprintf(
            '<p>Hello, %s</p><p>Please verify your email address by clicking the link below:</p><p><a href="%s">%s</a></p><p>If you didn\'t request this, you can ignore this email.</p>',
            htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8')
        );
        $text = sprintf('Verify your email address: %s', $verifyUrl);

        try {
            $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'api-key' => $this->brevoApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'sender' => [
                        'name' => $this->brevoSenderName,
                        'email' => $this->brevoSenderEmail,
                    ],
                    'to' => [
                        [
                            'email' => $user->getEmail(),
                        ],
                    ],
                    'subject' => $subject,
                    'htmlContent' => $html,
                    'textContent' => $text,
                ],
                'timeout' => 10,
                'max_duration' => 10,
            ]);

            $status = $response->getStatusCode();

            return $status >= 200 && $status < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    public function sendVerificationEmailTemplated(User $user, string $verificationUrl): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from($this->getTemplatedMailFrom())
                ->to(new Address((string) $user->getEmail()))
                ->subject('Please verify your email address')
                ->htmlTemplate('emails/verification.html.twig')
                ->context([
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                ]);

            $this->mailer->send($email);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function verifyToken(string $token): bool
    {
        /** @var EmailVerificationToken|null $emailToken */
        $emailToken = $this->entityManager->getRepository(EmailVerificationToken::class)->findOneBy([
            'token' => $token,
        ]);

        if (!$emailToken || $emailToken->isUsed()) {
            return false;
        }

        if ($emailToken->getExpiresAt() < new \DateTimeImmutable()) {
            return false;
        }

        $user = $emailToken->getUser();
        $user->setVerified(true);

        $emailToken->setUsedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return true;
    }

    private function buildVerifyUrl(string $token): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $baseUrl = trim($this->publicBaseUrl) !== ''
            ? rtrim($this->publicBaseUrl, '/')
            : (trim($this->defaultUri) !== ''
                ? rtrim($this->defaultUri, '/')
                : ($request ? rtrim($request->getSchemeAndHttpHost(), '/') : ''));

        if ($baseUrl === '') {
            // Fallback to relative URL (link may not work in emails, but prevents fatal errors).
            return $this->urlGenerator->generate('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_PATH);
        }

        return $baseUrl . $this->urlGenerator->generate('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    private function getTemplatedMailFrom(): Address
    {
        $line = trim($this->mailerFrom);
        if ($line !== '') {
            return Address::create($line);
        }

        return new Address($this->brevoSenderEmail, $this->brevoSenderName);
    }
}

