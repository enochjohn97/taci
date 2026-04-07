<?php
// src/Controller/SettingsController.php

namespace App\Controller;

use App\Entity\User;
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
#[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN|ROLE_STAFF')]
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
        $filename = 'taci-petroleum-backup-' . $timestamp . '.sql';

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
            'user_count' => $userCount,
            'sale_count' => $saleCount,
            'product_count' => $productCount,
        ]);
    }
}
