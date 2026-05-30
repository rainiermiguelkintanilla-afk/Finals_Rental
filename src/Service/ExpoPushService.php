<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends push notifications via Expo Push API (Android/iOS APK).
 */
final class ExpoPushService
{
    private const API_URL = 'https://exp.host/--/api/v2/push/send';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string>          $tokens Expo push tokens
     * @param array<string, mixed>  $data   Optional payload for the app
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return;
        }

        $messages = [];
        foreach ($tokens as $token) {
            if (!str_starts_with($token, 'ExponentPushToken[')) {
                continue;
            }
            $messages[] = [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'data' => $data,
            ];
        }

        if ($messages === []) {
            return;
        }

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = $this->httpClient->request('POST', self::API_URL, [
                    'json' => $chunk,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                ]);
                if ($response->getStatusCode() >= 400) {
                    $this->logger->warning('Expo push failed', [
                        'status' => $response->getStatusCode(),
                        'body' => $response->getContent(false),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Expo push error: '.$e->getMessage());
            }
        }
    }
}
