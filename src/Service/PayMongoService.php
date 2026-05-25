<?php

namespace App\Service;

use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PayMongoService
{
    private const API_BASE = 'https://api.paymongo.com/v1';

    private readonly string $secretKey;

    private readonly string $webhookSecret;

    private readonly string $appPublicUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly RealtimeEventBroadcaster $realtime,
        #[Autowire('%env(default::PAYMONGO_SECRET_KEY)%')]
        ?string $secretKey = null,
        #[Autowire('%env(default::PAYMONGO_WEBHOOK_SECRET)%')]
        ?string $webhookSecret = null,
        #[Autowire('%env(default::APP_PUBLIC_URL)%')]
        ?string $appPublicUrl = null,
    ) {
        // Empty .env values resolve to null; treat as disabled PayMongo (optional integration).
        $this->secretKey = $secretKey ?? '';
        $this->webhookSecret = $webhookSecret ?? '';
        $this->appPublicUrl = rtrim($appPublicUrl ?? '', '/');
    }

    public function isEnabled(): bool
    {
        return $this->secretKey !== '';
    }

    /**
     * @return array{checkoutUrl: string, linkId: string}
     */
    public function createCheckoutLink(Payment $payment): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('PayMongo is not configured. Set PAYMONGO_SECRET_KEY in .env.');
        }

        $amountCentavos = $this->amountToCentavos($payment->getAmount());
        $apartmentName = $payment->getApartment()?->getName() ?? 'Rent';
        $description = sprintf('Rainier Rentals — %s', $apartmentName);

        $response = $this->request('POST', '/links', [
            'data' => [
                'attributes' => [
                    'amount' => $amountCentavos,
                    'description' => $description,
                    'remarks' => $this->buildRemarks($payment),
                ],
            ],
        ]);

        $linkId = (string) ($response['data']['id'] ?? '');
        $checkoutUrl = (string) ($response['data']['attributes']['checkout_url'] ?? '');

        if ($linkId === '' || $checkoutUrl === '') {
            throw new \RuntimeException('PayMongo did not return a checkout link.');
        }

        $payment->setPaymongoLinkId($linkId);
        $this->entityManager->flush();

        return [
            'checkoutUrl' => $checkoutUrl,
            'linkId' => $linkId,
        ];
    }

    public function syncPaymentFromLink(Payment $payment): bool
    {
        $linkId = $payment->getPaymongoLinkId();
        if ($linkId === null || $linkId === '') {
            return false;
        }

        $response = $this->request('GET', '/links/' . $linkId);
        $status = (string) ($response['data']['attributes']['status'] ?? '');

        if ($status !== 'paid') {
            return false;
        }

        $this->markPaymentPaid($payment, null);

        return true;
    }

    public function handleWebhookPayload(string $rawBody, ?string $signatureHeader): void
    {
        if ($this->webhookSecret !== '' && !$this->verifySignature($rawBody, $signatureHeader)) {
            throw new \RuntimeException('Invalid PayMongo webhook signature.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid PayMongo webhook JSON.');
        }

        $eventType = (string) ($payload['data']['attributes']['type'] ?? '');
        $resource = $payload['data']['attributes']['data'] ?? null;

        if (!is_array($resource)) {
            return;
        }

        if ($eventType === 'link.payment.paid') {
            $this->handleLinkPaymentPaid($resource);

            return;
        }

        if ($eventType === 'payment.paid') {
            $this->handleStandalonePaymentPaid($resource);
        }
    }

    /**
     * @param array<string, mixed> $resource PayMongo payment resource from webhook
     */
    private function handleLinkPaymentPaid(array $resource): void
    {
        $attributes = $resource['attributes'] ?? [];
        if (!is_array($attributes)) {
            return;
        }

        $paymongoPaymentId = (string) ($resource['id'] ?? '');
        $linkId = '';
        $source = $attributes['source'] ?? [];
        if (is_array($source) && ($source['type'] ?? '') === 'link') {
            $linkId = (string) ($source['id'] ?? '');
        }

        $payment = $this->findPaymentByLinkId($linkId);
        if ($payment === null) {
            $payment = $this->findPaymentByRemarks((string) ($attributes['description'] ?? ''));
        }

        if ($payment === null) {
            return;
        }

        $this->markPaymentPaid($payment, $paymongoPaymentId !== '' ? $paymongoPaymentId : null);
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function handleStandalonePaymentPaid(array $resource): void
    {
        $attributes = $resource['attributes'] ?? [];
        if (!is_array($attributes)) {
            return;
        }

        $linkId = (string) ($attributes['source']['id'] ?? '');
        $payment = $this->findPaymentByLinkId($linkId);
        if ($payment === null) {
            $payment = $this->findPaymentByRemarks((string) ($attributes['description'] ?? ''));
        }

        if ($payment === null) {
            return;
        }

        $this->markPaymentPaid($payment, (string) ($resource['id'] ?? ''));
    }

    private function markPaymentPaid(Payment $payment, ?string $paymongoPaymentId): void
    {
        if ($payment->getStatus() === 'paid') {
            return;
        }

        $payment->setStatus('paid');
        $payment->setPaymentMethod('paymongo');
        $payment->setPaymentDate(new \DateTimeImmutable());
        if ($paymongoPaymentId !== null && $paymongoPaymentId !== '') {
            $payment->setPaymongoPaymentId($paymongoPaymentId);
        }

        $this->entityManager->flush();

        $this->realtime->publish('payment.paid', [
            'id' => $payment->getId(),
            'tenantId' => $payment->getTenant()?->getId(),
            'amount' => $payment->getAmount(),
            'apartment' => $payment->getApartment()?->getName(),
            'gateway' => 'paymongo',
        ]);
    }

    private function findPaymentByLinkId(string $linkId): ?Payment
    {
        if ($linkId === '') {
            return null;
        }

        return $this->entityManager->getRepository(Payment::class)->findOneBy([
            'paymongoLinkId' => $linkId,
        ]);
    }

    private function findPaymentByRemarks(string $remarks): ?Payment
    {
        if (!preg_match('/payment_id:(\d+)/', $remarks, $matches)) {
            return null;
        }

        return $this->entityManager->getRepository(Payment::class)->find((int) $matches[1]);
    }

    private function buildRemarks(Payment $payment): string
    {
        return sprintf('payment_id:%d', $payment->getId());
    }

    private function amountToCentavos(?string $amount): int
    {
        $value = round((float) $amount * 100);

        return max(100, (int) $value);
    }

    public function successRedirectUrl(): string
    {
        return rtrim($this->appPublicUrl, '/') . '/mobile/?payment=success';
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'auth_basic' => [$this->secretKey, ''],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        if ($body !== []) {
            $options['json'] = $body;
        }

        $response = $this->httpClient->request($method, self::API_BASE . $path, $options);
        $status = $response->getStatusCode();
        $decoded = $response->toArray(false);

        if ($status >= 400) {
            $detail = $decoded['errors'][0]['detail'] ?? $decoded['errors'][0]['code'] ?? 'PayMongo request failed';

            throw new \RuntimeException((string) $detail);
        }

        return $decoded;
    }

    private function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $timestamp = null;
        $signature = null;

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'li' || ($key === 'te' && $signature === null)) {
                $signature = $value;
            }
        }

        if ($timestamp === null || $signature === null) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
