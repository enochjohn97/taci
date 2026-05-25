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
    description: 'Creates default users for the application',
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

        $io->info('Updating/Creating default users...');

        $superPass = $_ENV['DEFAULT_SUPERADMIN_PASSWORD'] ?? getenv('DEFAULT_SUPERADMIN_PASSWORD') ?: 'password';
        $managerPass = $_ENV['DEFAULT_MANAGER_PASSWORD'] ?? getenv('DEFAULT_MANAGER_PASSWORD') ?: 'password';
        $staffPass = $_ENV['DEFAULT_STAFF_PASSWORD'] ?? getenv('DEFAULT_STAFF_PASSWORD') ?: 'password';

        $userRepo = $this->em->getRepository(User::class);

        // Super Admin
        $superAdmin = $userRepo->findOneBy(['username' => 'superadmin']) ?? new User();
        $superAdmin->setUsername('superadmin');
        $superAdmin->setEmail('superadmin@taci.com');
        $superAdmin->setRole(UserRole::ROLE_SUPER_ADMIN);
        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, $superPass));
        if (!$superAdmin->getId()) { $this->em->persist($superAdmin); }

        // Manager
        $manager = $userRepo->findOneBy(['username' => 'manager']) ?? new User();
        $manager->setUsername('manager');
        $manager->setEmail('manager@taci.com');
        $manager->setRole(UserRole::ROLE_MANAGER);
        $manager->setPassword($this->passwordHasher->hashPassword($manager, $managerPass));
        if (!$manager->getId()) { $this->em->persist($manager); }

        // Staff
        $staff = $userRepo->findOneBy(['username' => 'staff']) ?? new User();
        $staff->setUsername('staff');
        $staff->setEmail('staff@taci.com');
        $staff->setRole(UserRole::ROLE_STAFF);
        $staff->setPassword($this->passwordHasher->hashPassword($staff, $staffPass));
        if (!$staff->getId()) { $this->em->persist($staff); }

        $this->em->flush();

        $io->success('Default users created or updated. Passwords set to "password" if not specified in environment.');

        return Command::SUCCESS;
    }
}
