<?php
// src/Command/CreateDefaultUsersCommand.php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-default-users',
    description: 'Creates default users for TACI Petroleum system',
)]
class CreateDefaultUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Only create default users when explicitly enabled via environment variable
        $createFlag = $_ENV['CREATE_DEFAULT_USERS'] ?? getenv('CREATE_DEFAULT_USERS');
        if (!$createFlag || $createFlag !== '1') {
            $io->warning('Creation of default users is disabled. Set CREATE_DEFAULT_USERS=1 to enable.');
            return Command::SUCCESS;
        }

        $io->info('Creating default users for TACI Petroleum...');

        // Use environment-provided passwords when available, otherwise generate secure random passwords
        $superPass = $_ENV['DEFAULT_SUPERADMIN_PASSWORD'] ?? getenv('DEFAULT_SUPERADMIN_PASSWORD') ?: bin2hex(random_bytes(12));
        $managerPass = $_ENV['DEFAULT_MANAGER_PASSWORD'] ?? getenv('DEFAULT_MANAGER_PASSWORD') ?: bin2hex(random_bytes(12));
        $staffPass = $_ENV['DEFAULT_STAFF_PASSWORD'] ?? getenv('DEFAULT_STAFF_PASSWORD') ?: bin2hex(random_bytes(12));

        // Super Admin
        $superAdmin = new User();
        $superAdmin->setUsername('superadmin');
        $superAdmin->setEmail('superadmin@tacipetroleum.com');
        $superAdmin->setRole(UserRole::ROLE_SUPER_ADMIN);
        $hashedPassword = $this->passwordHasher->hashPassword($superAdmin, $superPass);
        $superAdmin->setPassword($hashedPassword);
        $this->em->persist($superAdmin);

        // Manager (Elevated)
        $manager = new User();
        $manager->setUsername('manager');
        $manager->setEmail('manager@tacipetroleum.com');
        $manager->setRole(UserRole::ROLE_MANAGER);
        $hashedPassword = $this->passwordHasher->hashPassword($manager, $managerPass);
        $manager->setPassword($hashedPassword);
        $this->em->persist($manager);

        // Staff
        $staff = new User();
        $staff->setUsername('staff');
        $staff->setEmail('staff@tacipetroleum.com');
        $staff->setRole(UserRole::ROLE_STAFF);
        $hashedPassword = $this->passwordHasher->hashPassword($staff, $staffPass);
        $staff->setPassword($hashedPassword);
        $this->em->persist($staff);

        $this->em->flush();

        $io->success('Default users created. For security reasons passwords are not displayed in logs.');
        $io->note('If you provided DEFAULT_*_PASSWORD environment variables those were used. Otherwise random passwords were generated.');

        return Command::SUCCESS;
    }
}
