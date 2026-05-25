<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list-users',
    description: 'List all users with their login details (email and roles)',
)]
class ListUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findAll();

        if (empty($users)) {
            $io->warning('No users found in the database.');
            $io->note('Run: php bin/console app:create-admin-user to create an admin user.');
            return Command::SUCCESS;
        }

        $io->title('Registered Users');

        $tableData = [];
        foreach ($users as $user) {
            $roles = $user->getRoles();
            $roleDisplay = implode(', ', array_map(function($role) {
                return $role === 'ROLE_ADMIN' ? 'Admin' : ($role === 'ROLE_STAFF' ? 'Staff' : 'User');
            }, array_filter($roles, fn($r) => $r !== 'ROLE_USER')));

            if (empty($roleDisplay)) {
                $roleDisplay = 'User';
            }

            $tableData[] = [
                $user->getId(),
                $user->getEmail(),
                $user->getFullName() ?? 'N/A',
                $roleDisplay,
                $user->getPassword() ? '✓' : '✗ (No password set)',
            ];
        }

        $io->table(
            ['ID', 'Email (Username)', 'Full Name', 'Role(s)', 'Password Set'],
            $tableData
        );

        $io->note([
            'Default password: "sazsad" (if password was set using default)',
            'You cannot view actual passwords as they are hashed for security.',
            'To reset a password, edit the user in the dashboard or use: php bin/console app:reset-user-password',
        ]);

        return Command::SUCCESS;
    }
}
















