<?php
// src/Controller/Admin/TransactionAdminController.php

namespace App\Controller\Admin;

use App\Entity\Sale;
use App\Entity\TransferLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/transactions')]
#[IsGranted('ROLE_SUB_ADMIN')]
class TransactionAdminController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'admin_transactions')]
    public function index(Request $request): Response
    {
        $qb = $this->em->getRepository(Sale::class)->createQueryBuilder('s')
            ->orderBy('s.createdAt','DESC');
        $sales = $qb->getQuery()->getResult();
        return $this->render('admin/transactions/index.html.twig', ['sales'=>$sales]);
    }

    #[Route('/{id}/approve', name: 'admin_transaction_approve', methods: ['POST'])]
    public function approve(Sale $sale): Response
    {
        $previous = json_encode(['status'=>$sale->getStatus(),'total'=>$sale->getTotalAmount()]);
        $sale->setStatus('approved');
        $this->em->persist($sale);

        $log = new TransferLog();
        $log->setSale($sale);
        $log->setAction('approve');
        $log->setPerformedBy($this->getUser());
        $log->setPreviousValues($previous);
        $log->setNewValues(json_encode(['status'=>'approved']));
        $this->em->persist($log);

        $this->em->flush();

        // notify cashier
        $notif = new \App\Entity\Notification();
        $notif->setUser($sale->getCashier());
        $notif->setType('transaction_approved');
        $notif->setMessage('Your transaction #' . $sale->getId() . ' was approved');
        $notif->setLink('/pos/receipt/' . $sale->getId());
        $this->em->persist($notif);
        $this->em->flush();

        return $this->redirectToRoute('admin_transactions');
    }

    #[Route('/{id}/decline', name: 'admin_transaction_decline', methods: ['POST'])]
    public function decline(Sale $sale, Request $request): Response
    {
        $reason = $request->request->get('reason');
        $previous = json_encode(['status'=>$sale->getStatus(),'total'=>$sale->getTotalAmount()]);
        $sale->setStatus('declined');
        $this->em->persist($sale);

        $log = new TransferLog();
        $log->setSale($sale);
        $log->setAction('decline');
        $log->setPerformedBy($this->getUser());
        $log->setPreviousValues($previous);
        $log->setNewValues(json_encode(['status'=>'declined','reason'=>$reason]));
        $this->em->persist($log);

        $this->em->flush();

        $notif = new \App\Entity\Notification();
        $notif->setUser($sale->getCashier());
        $notif->setType('transaction_declined');
        $notif->setMessage('Your transaction #' . $sale->getId() . ' was declined');
        $notif->setLink('/pos/receipt/' . $sale->getId());
        $this->em->persist($notif);
        $this->em->flush();

        return $this->redirectToRoute('admin_transactions');
    }
}
