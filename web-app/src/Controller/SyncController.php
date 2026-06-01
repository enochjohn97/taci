<?php
// src/Controller/SyncController.php

namespace App\Controller;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sync')]
class SyncController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'api_sync', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sync(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['transactions']) || !is_array($data['transactions'])) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        $count = 0;
        foreach ($data['transactions'] as $tx) {
            $audit = new AuditLog();
            $audit->setUser($this->getUser());
            $audit->setAction('offline_sync_tx');
            $audit->setModule('sync');
            $audit->setDescription(json_encode($tx));
            $audit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
            $audit->setUserAgent($request->headers->get('User-Agent'));
            $this->em->persist($audit);
            $count++;
        }

        $this->em->flush();

        return $this->json(['accepted' => $count], 202);
    }
}
