<?php
// src/Controller/DelegateController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/admin/delegates', name: 'app_delegates_')]
class DelegateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $delegates = $this->em->getRepository(User::class)->findByRole(UserRole::ROLE_SUB_ADMIN->value);

        return $this->render('admin/delegates/index.html.twig', [
            'delegates' => $delegates,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $fullName = $data['full_name'] ?? '';
            $permissionLevel = $data['permission_level'] ?? 'general';
            $selectedPermissions = $data['permissions'] ?? [];

            if (empty($fullName)) {
                $this->addFlash('error', 'Full name is required.');
                return $this->redirectToRoute('app_delegates_create');
            }

            // Auto-generate username
            $firstName = explode(' ', trim($fullName))[0];
            $randomSuffix = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $username = 'delegate_' . strtolower($firstName) . $randomSuffix;

            // Check for username uniqueness
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existingUser) {
                $this->addFlash('error', 'Generated username already exists. Please try again.');
                return $this->redirectToRoute('app_delegates_create');
            }

            // Auto-generate temporary password
            $tempPassword = $this->generateTemporaryPassword();

            $delegate = new User();
            $delegate->setUsername($username);
            $delegate->setEmail($data['email'] ?? '');
            $delegate->setRole(UserRole::ROLE_SUB_ADMIN);
            $delegate->setStatus('active');

            $hashedPassword = $this->passwordHasher->hashPassword($delegate, $tempPassword);
            $delegate->setPassword($hashedPassword);

            $this->em->persist($delegate);
            $this->em->flush();

            // Store in session for display
            $request->getSession()->set('delegate_created', [
                'username' => $username,
                'password' => $tempPassword,
                'email' => $delegate->getEmail(),
                'full_name' => $fullName,
                'permission_level' => $permissionLevel,
                'permissions' => $selectedPermissions,
            ]);

            return $this->redirectToRoute('app_delegates_credentials');
        }

        return $this->render('admin/delegates/create.html.twig');
    }

    #[Route('/credentials', name: 'credentials', methods: ['GET'])]
    public function showCredentials(Request $request): Response
    {
        $credentials = $request->getSession()->get('delegate_created');
        
        if (!$credentials) {
            return $this->redirectToRoute('app_delegates_index');
        }

        $request->getSession()->remove('delegate_created');

        return $this->render('admin/delegates/credentials.html.twig', [
            'credentials' => $credentials,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $delegate = $this->em->getRepository(User::class)->find($id);
        
        if (!$delegate || $delegate->getRole() !== UserRole::ROLE_SUB_ADMIN) {
            throw $this->createNotFoundException('Delegate not found.');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $delegate->setEmail($data['email'] ?? $delegate->getEmail());
            $delegate->setStatus($data['status'] ?? $delegate->getStatus());

            $this->em->persist($delegate);
            $this->em->flush();

            $this->addFlash('success', 'Delegate updated successfully.');
            return $this->redirectToRoute('app_delegates_index');
        }

        return $this->render('admin/delegates/edit.html.twig', [
            'delegate' => $delegate,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $delegate = $this->em->getRepository(User::class)->find($id);
        
        if (!$delegate || $delegate->getRole() !== UserRole::ROLE_SUB_ADMIN) {
            throw $this->createNotFoundException('Delegate not found.');
        }

        $this->em->remove($delegate);
        $this->em->flush();

        $this->addFlash('success', 'Delegate deleted successfully.');
        return $this->redirectToRoute('app_delegates_index');
    }

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): Response
    {
        $delegate = $this->em->getRepository(User::class)->find($id);
        
        if (!$delegate || $delegate->getRole() !== UserRole::ROLE_SUB_ADMIN) {
            throw $this->createNotFoundException('Delegate not found.');
        }

        $tempPassword = $this->generateTemporaryPassword();
        $hashedPassword = $this->passwordHasher->hashPassword($delegate, $tempPassword);
        $delegate->setPassword($hashedPassword);

        $this->em->persist($delegate);
        $this->em->flush();

        $request->getSession()->set('delegate_password_reset', [
            'username' => $delegate->getUsername(),
            'password' => $tempPassword,
        ]);

        $this->addFlash('success', 'Password reset. Credentials displayed.');
        return $this->redirectToRoute('app_delegates_show_password', ['id' => $id]);
    }

    #[Route('/{id}/show-password', name: 'show_password', methods: ['GET'])]
    public function showPassword(int $id, Request $request): Response
    {
        $credentials = $request->getSession()->get('delegate_password_reset');
        
        if (!$credentials) {
            return $this->redirectToRoute('app_delegates_index');
        }

        $request->getSession()->remove('delegate_password_reset');

        return $this->render('admin/delegates/show-password.html.twig', [
            'credentials' => $credentials,
        ]);
    }

    /**
     * Generate a temporary 8-character alphanumeric password
     */
    private function generateTemporaryPassword(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz!@#$%';
        $password = '';
        
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
}
