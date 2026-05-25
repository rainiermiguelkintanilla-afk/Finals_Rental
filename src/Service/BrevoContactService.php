<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BrevoContactService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(BREVO_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(BREVO_SENDER_EMAIL)%')]
        private readonly string $senderEmail,
        #[Autowire('%env(BREVO_SENDER_NAME)%')]
        private readonly string $senderName,
        #[Autowire('%env(BREVO_CONTACT_TO_EMAIL)%')]
        private readonly string $toEmail
    ) {
    }

    /**
     * @param array{name: string,email: string,subject: string,message: string} $payload
     */
    public function sendContactMessage(array $payload): bool
    {
        if ($this->apiKey === '') {
            return false;
        }

        $name = $payload['name'];
        $email = $payload['email'];
        $subject = $payload['subject'];
        $message = $payload['message'];

        $htmlContent = sprintf(
            '<p><strong>Name:</strong> %s</p><p><strong>Email:</strong> %s</p><p><strong>Subject:</strong> %s</p><p>%s</p>',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
            nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
        );

        $textContent = sprintf(
            "Name: %s\nEmail: %s\nSubject: %s\n\n%s",
            $name,
            $email,
            $subject,
            $message
        );

        try {
            $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'sender' => [
                        'name' => $this->senderName,
                        'email' => $this->senderEmail,
                    ],
                    'to' => [
                        [
                            'email' => $this->toEmail,
                        ],
                    ],
                    'subject' => $subject,
                    'htmlContent' => $htmlContent,
                    'textContent' => $textContent,
                    'replyTo' => [
                        'email' => $email,
                        'name' => $name,
                    ],
                ],
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

