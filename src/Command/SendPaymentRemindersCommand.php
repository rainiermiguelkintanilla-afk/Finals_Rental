<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Service\PushNotificationDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-payment-reminders',
    description: 'Send push/email reminders for rent payments due within 3 days',
)]
final class SendPaymentRemindersCommand extends Command
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly UserRepository $users,
        private readonly PushNotificationDispatcher $notifications,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dueSoon = $this->payments->findDueWithinDays(3);
        $sent = 0;

        foreach ($dueSoon as $payment) {
            $tenant = $payment->getTenant();
            if ($tenant === null || $tenant->getId() === null) {
                continue;
            }

            $user = $this->users->findOneByTenantId((int) $tenant->getId());
            if (!$user instanceof User || !$user->isNotifyPaymentReminders()) {
                continue;
            }

            $apartment = $payment->getApartment()?->getName() ?? 'your unit';
            $due = $payment->getDueDate()?->format('M j, Y') ?? 'soon';
            $this->notifications->notifyUser(
                $user,
                'Payment reminder',
                sprintf('Rent of ₱%s for %s is due on %s.', $payment->getAmount(), $apartment, $due),
                ['screen' => 'payments', 'event' => 'payment.reminder'],
            );
            ++$sent;
        }

        $io->success(sprintf('Sent %d payment reminder(s).', $sent));

        return Command::SUCCESS;
    }
}
