<?php

namespace App\Service;

use App\Entity\EmailVerificationToken;
use App\Message\SendVerificationEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;

/**
 * Queues verification email on the async transport (fast) and, in dev only,
 * starts a one-shot messenger consumer so mail is sent without running a worker manually.
 */
final class VerificationEmailDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {
    }

    public function dispatch(EmailVerificationToken $token): void
    {
        $id = $token->getId();
        if ($id === null) {
            $this->logger->error('Cannot queue verification email: token has no id.');

            return;
        }

        $this->bus->dispatch(new SendVerificationEmailMessage($id));

        if ($this->environment === 'dev') {
            $this->spawnOneShotConsumer();
        }
    }

    private function spawnOneShotConsumer(): void
    {
        try {
            $process = new Process(
                [PHP_BINARY, $this->projectDir.'/bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=120'],
                $this->projectDir
            );
            $process->disableOutput();
            $process->start();
        } catch (\Throwable $e) {
            $this->logger->notice('Could not spawn messenger consumer (run `php bin/console messenger:consume async` manually): '.$e->getMessage());
        }
    }
}
