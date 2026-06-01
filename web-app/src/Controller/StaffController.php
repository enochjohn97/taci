<?php
// src/Controller/StaffController.php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/staff')]
class StaffController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'api_staff_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(): JsonResponse
    {
        $qb = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.role = :role')
            ->andWhere('u.status = :status')
            ->setParameter('role', 'ROLE_STAFF')
            ->setParameter('status', 'active')
            ->orderBy('u.username', 'ASC');

        $users = $qb->getQuery()->getResult();

        $payload = array_map(function($u) {
            return ['id' => $u->getId(), 'username' => $u->getUsername(), 'email' => $u->getEmail()];
        }, $users);

        return $this->json($payload);
    }
}
