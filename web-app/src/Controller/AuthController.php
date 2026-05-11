<?php
// src/Controller/AuthController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserRole;
use App\Entity\PasswordReset;
use App\Form\LoginFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/', name: 'app_index')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        return $this->redirectToRoute('app_role_select');
    }

    #[Route('/role-select', name: 'app_role_select')]
    public function roleSelect(): Response
    {
        return $this->render('auth/role-select.html.twig');
    }

    #[Route('/login/{role?}', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        ?string $role,
        AuthenticationUtils $authenticationUtils,
        Request $request
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $requestedRole = $role ?: $request->query->get('role');
        if (!$requestedRole) {
            return $this->redirectToRoute('app_role_select');
        }

        $normalizedRole = str_replace('_', '-', strtolower($requestedRole));
        $validRoles = ['super-admin', 'sub-admin', 'staff'];
        if (!in_array($normalizedRole, $validRoles, true)) {
            return $this->redirectToRoute('app_role_select');
        }

        $roleDisplay = match($normalizedRole) {
            'super-admin' => 'Super Admin',
            'sub-admin' => 'Sub Admin',
            'staff' => 'Staff',
        };

        $hasDelegates = (bool) count($this->userRepository->findByRole(UserRole::ROLE_SUB_ADMIN->value));

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'role' => $normalizedRole,
            'role_display' => $roleDisplay,
            'has_delegates' => $hasDelegates,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): Response
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/password-recovery', name: 'app_password_recovery', methods: ['GET', 'POST'])]
    public function passwordRecovery(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            // Always return success message for security (prevent email enumeration)
            if ($user) {
                // Generate recovery token
                $token = bin2hex(random_bytes(32));
                
                // Create password reset record
                $passwordReset = new \App\Entity\PasswordReset($user, $token, 60);
                $em->persist($passwordReset);
                $em->flush();
                
                // Send recovery email
                $resetUrl = $this->generateUrl('app_password_reset', ['token' => $token], 0);
                $emailMessage = new Email();
                $emailMessage
                    ->from($this->getParameter('noreply_email'))
                    ->to($user->getEmail())
                    ->subject('Password Recovery - TACI Petroleum')
                    ->html("
                        <h1>Password Recovery</h1>
                        <p>Hello, " . $user->getUsername() . "</p>
                        <p>Click the link below to reset your password. It expires at " . $passwordReset->getExpiresAt()->format('H:i') . "</p>
                        <p><a href='" . $resetUrl . "'>Reset Password</a></p>
                    ");

                $mailer->send($emailMessage);
            }

            $this->addFlash('success', 'If an account exists with this email, you will receive recovery instructions.');
            return $this->redirectToRoute('app_role_select');
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'auth_password_recovery',
        ]);
    }

    #[Route('/password-reset/{token}', name: 'app_password_reset', methods: ['GET', 'POST'])]
    public function passwordReset(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Find valid password reset token
        $passwordReset = $em->getRepository(\App\Entity\PasswordReset::class)
            ->findOneBy(['token' => $token]);

        if (!$passwordReset || !$passwordReset->isValid()) {
            $this->addFlash('error', 'Invalid or expired password recovery link');
            return $this->redirectToRoute('app_password_recovery');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            if (empty($password) || empty($confirmPassword)) {
                $this->addFlash('error', 'Password fields are required');
                return $this->redirectToRoute('app_password_reset', ['token' => $token]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match');
                return $this->redirectToRoute('app_password_reset', ['token' => $token]);
            }

            if (strlen($password) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters long');
                return $this->redirectToRoute('app_password_reset', ['token' => $token]);
            }

            // Update password
            $user = $passwordReset->getUser();
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Mark token as used
            $passwordReset->setUsed(true);

            $em->persist($user);
            $em->persist($passwordReset);
            $em->flush();

            $this->addFlash('success', 'Password has been reset successfully. Please log in with your new password.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'auth_password_reset',
            'token' => $token,
        ]);
    }
}
