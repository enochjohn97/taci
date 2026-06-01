<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-manager')]
class CreateManagerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'manager']);
        if (!$user) {
            $user = new User();
            $user->setUsername('manager');
            $user->setEmail('manager@example.com');
            $user->setRole(UserRole::ROLE_MANAGER);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'manager@123'));
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $output->writeln('Manager created successfully.');
        } else {
            $user->setPassword($this->passwordHasher->hashPassword($user, 'manager@123'));
            $this->entityManager->flush();
            $output->writeln('Manager password updated successfully.');
        }

        return Command::SUCCESS;
    }
}
