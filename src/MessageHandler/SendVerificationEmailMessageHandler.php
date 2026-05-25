<?php

namespace App\MessageHandler;

use App\Entity\EmailVerificationToken;
use App\Message\SendVerificationEmailMessage;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendVerificationEmailMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(SendVerificationEmailMessage $message): void
    {
        $token = $this->entityManager->find(EmailVerificationToken::class, $message->emailVerificationTokenId);
        if (!$token) {
            $this->logger->error('Verification token not found for async email send.', [
                'id' => $message->emailVerificationTokenId,
            ]);

            return;
        }

        $ok = $this->emailVerificationService->sendVerificationEmail($token->getUser(), $token);
        if (!$ok) {
            $this->logger->warning('Verification email was not accepted by Brevo.', [
                'email' => $token->getUser()->getEmail(),
            ]);
        }
    }
}
