<?php
// src/Controller/PosTransactionsController.php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos')]
class PosTransactionsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/transactions', name: 'app_pos_transactions')]
    public function index(): Response
    {
        // Only allow Manager, Sub Admin, or Super Admin
        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_SUB_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Access Denied.');
        }

        // Fetch all digital payments from Payment entity
        $payments = $this->em->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('p.method IN (:methods)')
            ->setParameter('methods', ['card', 'transfer', 'ussd'])
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Fetch all digital transactions from Transaction entity
        $transactions = $this->em->getRepository(Transaction::class)
            ->createQueryBuilder('t')
            ->where('t.paymentMethod IN (:methods)')
            ->setParameter('methods', ['card', 'transfer', 'ussd'])
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Combine and sort them by date descending
        $allTx = [];
        foreach ($payments as $p) {
            $allTx[] = [
                'source' => 'Fuel/Store',
                'reference' => $p->getReference() ?? 'Pending Ref',
                'amount' => $p->getAmount(),
                'method' => $p->getMethod(),
                'status' => $p->getStatus(),
                'date' => $p->getCreatedAt(),
            ];
        }
        foreach ($transactions as $t) {
            $allTx[] = [
                'source' => 'Reception POS',
                'reference' => $t->getReference() ?? 'Pending Ref',
                'amount' => $t->getTotal(),
                'method' => $t->getPaymentMethod(),
                'status' => $t->getStatus(),
                'date' => $t->getCreatedAt(),
            ];
        }

        usort($allTx, fn($a, $b) => $b['date'] <=> $a['date']);

        return $this->render('pos/transactions.html.twig', [
            'transactions' => $allTx,
        ]);
    }
}
