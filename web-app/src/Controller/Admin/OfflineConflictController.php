<?php
// src/Controller/Admin/OfflineConflictController.php

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/offline-conflicts')]
class OfflineConflictController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'admin_offline_conflicts', methods: ['GET'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function index(): Response
    {
        $repo = $this->em->getRepository(AuditLog::class);
        $qb = $repo->createQueryBuilder('a')
            ->where('a.action = :act')
            ->setParameter('act', 'offline_sync_conflict')
            ->orderBy('a.timestamp', 'DESC')
            ->setMaxResults(200);

        $conflicts = $qb->getQuery()->getResult();

        return $this->render('admin/offline-conflicts.html.twig', ['conflicts' => $conflicts]);
    }

    #[Route('/{id}/resolve', name: 'admin_offline_conflict_resolve', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function resolve(AuditLog $audit, Request $request): Response
    {
        // mark resolved by updating description to include resolved metadata
        $desc = $audit->getDescription();
        $payload = null;
        try { $payload = json_decode($desc, true); } catch (\Throwable $e) { $payload = ['raw' => $desc]; }

        if (!is_array($payload)) $payload = ['raw' => $desc];
        $payload['resolved'] = true;
        $payload['resolvedBy'] = $this->getUser()->getId();
        $payload['resolvedAt'] = (new \DateTime())->format(DATE_ATOM);

        $audit->setDescription(json_encode($payload));
        $this->em->persist($audit);

        // write a new audit entry noting resolution
        $note = new AuditLog();
        $note->setUser($this->getUser());
        $note->setAction('offline_conflict_resolved');
        $note->setModule('sync');
        $note->setDescription(json_encode(['originalAuditId' => $audit->getId(), 'resolver' => $this->getUser()->getId()]));
        $note->setIpAddress($request->getClientIp() ?? '0.0.0.0');
        $note->setUserAgent($request->headers->get('User-Agent'));
        $this->em->persist($note);

        $this->em->flush();

        $this->addFlash('success', 'Conflict marked as resolved.');
        return $this->redirectToRoute('admin_offline_conflicts');
    }
}
