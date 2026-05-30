<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transactional alert emails via Brevo (falls back to no-op when not configured).
 */
final class NotificationEmailService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(BREVO_API_KEY)%')]
        private readonly string $brevoApiKey,
        #[Autowire('%env(BREVO_SENDER_EMAIL)%')]
        private readonly string $brevoSenderEmail,
        #[Autowire('%env(BREVO_SENDER_NAME)%')]
        private readonly string $brevoSenderName,
    ) {
    }

    public function sendAlert(User $user, string $subject, string $htmlBody): void
    {
        if (!$user->isNotifyEmail()) {
            return;
        }

        $email = $user->getEmail();
        if ($email === null || $email === '') {
            return;
        }

        if ($this->brevoApiKey === '' || $this->brevoSenderEmail === '') {
            $this->logger->info('Notification email skipped (Brevo not configured)', [
                'to' => $email,
                'subject' => $subject,
            ]);

            return;
        }

        try {
            $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'api-key' => $this->brevoApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'sender' => [
                        'name' => $this->brevoSenderName ?: "Rainier's Real Estate",
                        'email' => $this->brevoSenderEmail,
                    ],
                    'to' => [['email' => $email, 'name' => $user->getDisplayName()]],
                    'subject' => $subject,
                    'htmlContent' => $htmlBody,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Notification email failed: '.$e->getMessage());
        }
    }
}
