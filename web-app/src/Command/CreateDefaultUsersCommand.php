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

        $superPass = $_ENV['DEFAULT_SUPERADMIN_PASSWORD'] ?? getenv('DEFAULT_SUPERADMIN_PASSWORD') ?: 'superadmin@123';
        $managerPass = $_ENV['DEFAULT_MANAGER_PASSWORD'] ?? getenv('DEFAULT_MANAGER_PASSWORD') ?: 'manager@123';
        $staffPass = $_ENV['DEFAULT_STAFF_PASSWORD'] ?? getenv('DEFAULT_STAFF_PASSWORD') ?: 'staff@123';

        $conn = $this->em->getConnection();
        $dummy = new User();

        $usersToProcess = [
            ['username' => 'superadmin', 'email' => 'superadmin@taci.com', 'role' => UserRole::ROLE_SUPER_ADMIN->value, 'pass' => $superPass],
            ['username' => 'manager', 'email' => 'manager@taci.com', 'role' => UserRole::ROLE_MANAGER->value, 'pass' => $managerPass],
            ['username' => 'staff', 'email' => 'staff@taci.com', 'role' => UserRole::ROLE_STAFF->value, 'pass' => $staffPass],
        ];

        foreach ($usersToProcess as $u) {
            $hashed = $this->passwordHasher->hashPassword($dummy, $u['pass']);
            
            $existingId = $conn->fetchOne('SELECT id FROM "users" WHERE username = ?', [$u['username']]);
            
            if ($existingId) {
                $conn->executeStatement('UPDATE "users" SET password = ?, email = ?, role = ? WHERE id = ?', [
                    $hashed, $u['email'], $u['role'], $existingId
                ]);
            } else {
                $conn->executeStatement(
                    'INSERT INTO "users" (username, email, role, password, status, dark_mode_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $u['username'], 
                        $u['email'], 
                        $u['role'], 
                        $hashed, 
                        'active', 
                        '0', 
                        (new \DateTime())->format('Y-m-d H:i:s'), 
                        (new \DateTime())->format('Y-m-d H:i:s')
                    ]
                );
            }
        }

        $io->success('Default users created or updated. Passwords set to "password" if not specified in environment.');

        return Command::SUCCESS;
    }
}
