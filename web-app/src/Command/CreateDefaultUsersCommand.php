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
        $io->info('Creating default users for TACI Petroleum...');

        // Super Admin
        $superAdmin = new User();
        $superAdmin->setUsername('superadmin');
        $superAdmin->setEmail('superadmin@tacipetroleum.com');
        $superAdmin->setRole(UserRole::ROLE_SUPER_ADMIN);
        $hashedPassword = $this->passwordHasher->hashPassword($superAdmin, 'superadmin@123');
        $superAdmin->setPassword($hashedPassword);
        $this->em->persist($superAdmin);
        $io->success('Created Super Admin: superadmin / superadmin@123');

        // Sub Admin
        $subAdmin = new User();
        $subAdmin->setUsername('subadmin');
        $subAdmin->setEmail('subadmin@tacipetroleum.com');
        $subAdmin->setRole(UserRole::ROLE_SUB_ADMIN);
        $hashedPassword = $this->passwordHasher->hashPassword($subAdmin, 'subadmin@123');
        $subAdmin->setPassword($hashedPassword);
        $this->em->persist($subAdmin);
        $io->success('Created Sub Admin: subadmin / subadmin@123');

        // Staff
        $staff = new User();
        $staff->setUsername('staff');
        $staff->setEmail('staff@tacipetroleum.com');
        $staff->setRole(UserRole::ROLE_STAFF);
        $hashedPassword = $this->passwordHasher->hashPassword($staff, 'staff@123');
        $staff->setPassword($hashedPassword);
        $this->em->persist($staff);
        $io->success('Created Staff: staff / staff@123');

        $this->em->flush();

        $io->info('All default users created successfully!');

        return Command::SUCCESS;
    }
}
