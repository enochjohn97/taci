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
    private \App\Service\NotificationService $notificationService;

    public function __construct(private EntityManagerInterface $em, \App\Service\NotificationService $notificationService) {
        $this->notificationService = $notificationService;
    }

    #[Route('', name: 'admin_transactions')]
    public function index(Request $request): Response
    {
        $qb = $this->em->getRepository(Sale::class)->createQueryBuilder('s')
            ->orderBy('s.createdAt','DESC');
        $sales = $qb->getQuery()->getResult();
        return $this->render('admin/transactions/index.html.twig', ['sales'=>$sales]);
    }

    #[Route('/{id}/approve', name: 'admin_transaction_approve', methods: ['POST'])]
    public function approve(Sale $sale, Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('approve' . $sale->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('admin_transactions');
        }

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

        // notify cashier via NotificationService
        if ($sale->getCashier()) {
            $this->notificationService->sendNotification(
                $sale->getCashier(),
                'transaction_approved',
                'Your transaction #' . $sale->getId() . ' was approved',
                '/pos/receipt/' . $sale->getId()
            );
        }

        return $this->redirectToRoute('admin_transactions');
    }

    #[Route('/{id}/decline', name: 'admin_transaction_decline', methods: ['POST'])]
    public function decline(Sale $sale, Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('decline' . $sale->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('admin_transactions');
        }

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

        if ($sale->getCashier()) {
            $this->notificationService->sendNotification(
                $sale->getCashier(),
                'transaction_declined',
                'Your transaction #' . $sale->getId() . ' was declined',
                '/pos/receipt/' . $sale->getId()
            );
        }

        return $this->redirectToRoute('admin_transactions');
    }

    #[Route('/{id}/edit', name: 'admin_transaction_edit', methods: ['GET','POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Sale $sale, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_transaction' . $sale->getId(), $token)) {
                $this->addFlash('error', 'Invalid CSRF token');
                return $this->redirectToRoute('admin_transactions');
            }

            $newAmount = $request->request->get('amount');
            $newStatus = $request->request->get('status');

            $previous = json_encode(['status'=>$sale->getStatus(),'total'=>$sale->getTotalAmount()]);

            if (is_numeric($newAmount)) {
                $sale->setTotalAmount((float)$newAmount);
            }
            if (!empty($newStatus)) {
                $sale->setStatus($newStatus);
            }

            $this->em->persist($sale);

            $log = new TransferLog();
            $log->setSale($sale);
            $log->setAction('correction');
            $log->setPerformedBy($this->getUser());
            $log->setPreviousValues($previous);
            $log->setNewValues(json_encode(['status'=>$sale->getStatus(),'total'=>$sale->getTotalAmount()]));
            $this->em->persist($log);

            $this->em->flush();

            if ($sale->getCashier()) {
                $this->notificationService->sendNotification(
                    $sale->getCashier(),
                    'transaction_corrected',
                    'Your transaction #' . $sale->getId() . ' was updated by admin',
                    '/pos/receipt/' . $sale->getId()
                );
            }

            return $this->redirectToRoute('admin_transactions');
        }

        return $this->render('admin/transactions/edit.html.twig', ['sale'=>$sale]);
    }
}
