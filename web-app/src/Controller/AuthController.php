<?php
// src/Controller/AuthController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserRole;
use App\Form\LoginFormType;
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
    #[Route('/role-select', name: 'app_role_select')]
    public function roleSelect(): Response
    {
        return $this->render('auth/role-select.html.twig');
    }

    #[Route('/login/{role}', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        string $role,
        AuthenticationUtils $authenticationUtils,
        Request $request
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Validate role parameter
        $validRoles = ['super-admin', 'sub-admin', 'staff'];
        if (!in_array($role, $validRoles)) {
            return $this->redirectToRoute('app_role_select');
        }

        $roleDisplay = match($role) {
            'super-admin' => 'Super Admin',
            'sub-admin' => 'Sub Admin',
            'staff' => 'Staff',
        };

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'role' => $role,
            'role_display' => $roleDisplay,
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
            $email = $request->getPayload()->getString('email');
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                // Generate recovery token
                $token = bin2hex(random_bytes(32));
                // TODO: Store token in database with expiry
                
                // Send recovery email
                $emailMessage = new Email();
                $emailMessage
                    ->from('noreply@tacipetroleum.com')
                    ->to($user->getEmail())
                    ->subject('Password Recovery - TACI Petroleum')
                    ->html($this->renderView('auth/password-recovery-email.html.twig', [
                        'user' => $user,
                        'token' => $token,
                    ]));

                $mailer->send($emailMessage);

                $this->addFlash('success', 'Check your email for password recovery instructions.');
            } else {
                $this->addFlash('warning', 'If an account exists with this email, you will receive recovery instructions.');
            }

            return $this->redirectToRoute('app_role_select');
        }

        return $this->render('auth/password-recovery.html.twig');
    }
}
