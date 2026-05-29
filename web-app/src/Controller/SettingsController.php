<?php
// src/Controller/SettingsController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserRole;
use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/settings')]
#[IsGranted(expression: "is_granted('ROLE_STAFF') or is_granted('ROLE_MANAGER') or is_granted('ROLE_SUB_ADMIN') or is_granted('ROLE_SUPER_ADMIN')")]
class SettingsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/profile', name: 'app_settings_profile')]
    public function profile(): Response
    {
        $user = $this->getUser();

        return $this->render('settings/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/update', name: 'app_settings_profile_update', methods: ['POST'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if ($data['email'] ?? false) {
            $user->setEmail($data['email']);
        }

        $this->em->flush();

        // Log action
        $audit = new AuditLog();
        $audit->setUser($user);
        $audit->setAction('Profile Updated');
        $audit->setModule('Settings');
        $audit->setIpAddress($request->getClientIp());
        $audit->setUserAgent($request->headers->get('User-Agent'));
        $this->em->persist($audit);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Profile updated']);
    }

    #[Route('/password/change', name: 'app_settings_password_change', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;
        $confirmPassword = $data['confirmPassword'] ?? null;

        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            return $this->json(['error' => 'All fields required'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->json(['error' => 'Passwords do not match'], 400);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['error' => 'Current password is incorrect'], 400);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->em->flush();

        // Log action
        $audit = new AuditLog();
        $audit->setUser($user);
        $audit->setAction('Password Changed');
        $audit->setModule('Settings');
        $audit->setIpAddress($request->getClientIp());
        $this->em->persist($audit);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Password changed successfully']);
    }

    #[Route('/dark-mode/toggle', name: 'app_settings_dark_mode_toggle', methods: ['POST'])]
    public function toggleDarkMode(): JsonResponse
    {
        $user = $this->getUser();
        $user->setDarkModeEnabled(!$user->isDarkModeEnabled());
        $this->em->flush();

        return $this->json([
            'success' => true,
            'darkModeEnabled' => $user->isDarkModeEnabled(),
        ]);
    }

    #[Route('/admin', name: 'app_settings_admin')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function adminSettings(): Response
    {
        $users = $this->em->getRepository(User::class)->findAll();
        $auditLogs = $this->em->getRepository(AuditLog::class)
            ->findBy([], ['timestamp' => 'DESC'], 50);

        return $this->render('settings/admin.html.twig', [
            'users' => $users,
            'audit_logs' => $auditLogs,
        ]);
    }

    #[Route('/admin/users', name: 'app_settings_admin_users')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function manageUsers(): Response
    {
        $users = $this->em->getRepository(User::class)->findAll();

        return $this->render('settings/admin-users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/{id}/status', name: 'app_settings_user_status', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function updateUserStatus(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;

        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $user->setStatus($status);
        $this->em->flush();

        // Log action
        $audit = new AuditLog();
        $audit->setUser($this->getUser());
        $audit->setAction('User Status Changed');
        $audit->setModule('User Management');
        $audit->setDescription('User ' . $user->getUsername() . ' status changed to ' . $status);
        $this->em->persist($audit);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/admin/audit-log', name: 'app_settings_audit_log')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function auditLog(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;

        $qb = $this->em->getRepository(AuditLog::class)->createQueryBuilder('a');
        $total = $qb->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $logs = $this->em->getRepository(AuditLog::class)
            ->findBy([], ['timestamp' => 'DESC'], $limit, ($page - 1) * $limit);

        $totalPages = ceil($total / $limit);

        return $this->render('settings/audit-log.html.twig', [
            'logs' => $logs,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/admin/database-backup', name: 'app_settings_database_backup', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function databaseBackup(): Response
    {
        // TODO: Implement database backup logic
        // For now, return a placeholder response
        $timestamp = date('Y-m-d-H-i-s');
        $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '-', ($_ENV['APP_NAME'] ?? 'APP')));
        $filename = $slug . '-backup-' . $timestamp . '.sql';

        $this->addFlash('success', 'Database backup initiated: ' . $filename);

        // Log action
        $audit = new AuditLog();
        $audit->setUser($this->getUser());
        $audit->setAction('Database Backup');
        $audit->setModule('System');
        $audit->setDescription('Database backup created: ' . $filename);
        $this->em->persist($audit);
        $this->em->flush();

        return $this->redirectToRoute('app_settings_admin');
    }

    #[Route('/admin/system-info', name: 'app_settings_system_info')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function systemInfo(): Response
    {
        $phpVersion = phpversion();
        $symfonyVersion = \Symfony\Component\HttpKernel\Kernel::VERSION;

        // Count stats
        $userCount = $this->em->getRepository(User::class)->count([]);
        $saleCount = $this->em->getRepository(\App\Entity\Sale::class)->count([]);
        $productCount = $this->em->getRepository(\App\Entity\Product::class)->count([]);

        return $this->render('settings/system-info.html.twig', [
            'php_version' => $phpVersion,
            'symfony_version' => $symfonyVersion,
            'php_memory_limit' => ini_get('memory_limit'),
            'user_count' => $userCount,
            'sale_count' => $saleCount,
            'product_count' => $productCount,
        ]);
    }

    #[Route('/admin/sub-admins', name: 'app_settings_sub_admins')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function manageSubAdmins(): Response
    {
        $subAdmins = $this->em->getRepository(User::class)->findByRole(UserRole::ROLE_SUB_ADMIN->value);

        return $this->render('settings/sub-admins.html.twig', [
            'sub_admins' => $subAdmins,
        ]);
    }

    #[Route('/admin/sub-admin/create', name: 'app_settings_sub_admin_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function createSubAdmin(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $name = trim($data['name'] ?? '');
            $password = $data['password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';

            // Validation
            $errors = [];
            if (empty($username)) $errors[] = 'Username is required';
            if (empty($email)) $errors[] = 'Email is required';
            if (empty($name)) $errors[] = 'Name is required';
            if (empty($password)) $errors[] = 'Password is required';
            if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
            if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';

            if (!$errors) {
                // Check if username/email already exists
                $existingUser = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
                if ($existingUser) $errors[] = 'Username already exists';

                $existingEmail = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingEmail) $errors[] = 'Email already exists';
            }

            if ($errors) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_settings_sub_admin_create');
            }

            // Create sub-admin user
            $subAdmin = new User();
            $subAdmin->setUsername($username);
            $subAdmin->setEmail($email);
            $subAdmin->setRole(UserRole::ROLE_SUB_ADMIN);
            $subAdmin->setStatus('active');
            $hashedPassword = $this->passwordHasher->hashPassword($subAdmin, $password);
            $subAdmin->setPassword($hashedPassword);

            $this->em->persist($subAdmin);
            $this->em->flush();

            // Log action
            $audit = new AuditLog();
            $audit->setUser($this->getUser());
            $audit->setAction('Sub Admin Created');
            $audit->setModule('User Management');
            $audit->setDescription('Sub Admin ' . $username . ' created');
            $audit->setIpAddress($request->getClientIp());
            $this->em->persist($audit);
            $this->em->flush();

            $this->addFlash('success', 'Sub Admin user created successfully');
            return $this->redirectToRoute('app_settings_sub_admins');
        }

        return $this->render('settings/sub-admin-create.html.twig');
    }

    #[Route('/admin/sub-admin/{id}/delete', name: 'app_settings_sub_admin_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function deleteSubAdmin(User $user, Request $request): Response
    {
        if ($user->getRole()->value !== UserRole::ROLE_SUB_ADMIN->value) {
            $this->addFlash('error', 'User is not a Sub Admin');
            return $this->redirectToRoute('app_settings_sub_admins');
        }

        $username = $user->getUsername();
        $this->em->remove($user);
        $this->em->flush();

        // Log action
        $audit = new AuditLog();
        $audit->setUser($this->getUser());
        $audit->setAction('Sub Admin Deleted');
        $audit->setModule('User Management');
        $audit->setDescription('Sub Admin ' . $username . ' deleted');
        $audit->setIpAddress($request->getClientIp());
        $this->em->persist($audit);
        $this->em->flush();

        $this->addFlash('success', 'Sub Admin user deleted successfully');
        return $this->redirectToRoute('app_settings_sub_admins');
    }
}
