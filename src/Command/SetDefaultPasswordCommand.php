<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:set-default-password',
    description: 'Set default password "sazsad" for all users without a password',
)]
class SetDefaultPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->entityManager->getRepository(User::class)->findAll();
        $defaultPassword = 'sazsad';
        $updatedCount = 0;

        foreach ($users as $user) {
            if (!$user->getPassword() || $user->getPassword() === '') {
                $user->setPassword($this->passwordHasher->hashPassword($user, $defaultPassword));
                $this->entityManager->persist($user);
                $updatedCount++;
                $io->writeln("Updated password for user: {$user->getEmail()}");
            }
        }

        $this->entityManager->flush();

        if ($updatedCount > 0) {
            $io->success("Updated passwords for {$updatedCount} user(s) to default password '{$defaultPassword}'");
        } else {
            $io->info('No users needed password updates.');
        }

        return Command::SUCCESS;
    }
}
















