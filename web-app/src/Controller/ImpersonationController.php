<?php
// src/Controller/ImpersonationController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/auth')]
class ImpersonationController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/impersonate', name: 'app_impersonate', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function impersonate(Request $request): RedirectResponse
    {
        $targetId = $request->request->get('userId');
        if (!$targetId) {
            $this->addFlash('error', 'Missing target user');
            return $this->redirectToRoute('app_role_select');
        }

        $target = $this->em->getRepository(User::class)->find($targetId);
        if (!$target) {
            $this->addFlash('error', 'Target user not found');
            return $this->redirectToRoute('app_role_select');
        }

        // Audit the impersonation attempt
        $audit = new AuditLog();
        $audit->setUser($this->getUser());
        $audit->setAction('impersonation_start');
        $audit->setModule('auth');
        $audit->setDescription(sprintf('User %s impersonated %s (id=%d)', $this->getUser()->getUsername(), $target->getUsername(), $target->getId()));
        $audit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
        $audit->setUserAgent($request->headers->get('User-Agent'));
        $this->em->persist($audit);
        $this->em->flush();

        // Redirect to Symfony switch_user parameter to activate impersonation
        // The switch_user parameter (configured as _switch_user) will perform the token swap
        return $this->redirectToRoute('app_index', ['_switch_user' => $target->getUsername()]);
    }

    #[Route('/impersonate/exit', name: 'app_impersonate_exit', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function impersonateExit(Request $request): RedirectResponse
    {
        // Log exit
        $audit = new AuditLog();
        $audit->setUser($this->getUser());
        $audit->setAction('impersonation_end');
        $audit->setModule('auth');
        $audit->setDescription('Impersonation session ended');
        $audit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
        $audit->setUserAgent($request->headers->get('User-Agent'));
        $this->em->persist($audit);
        $this->em->flush();

        return $this->redirectToRoute('app_index', ['_switch_user' => '_exit']);
    }
}
