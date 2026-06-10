<?php
// src/Controller/SupplierController.php

namespace App\Controller;

use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventory')]
class SupplierController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/suppliers', name: 'inventory_suppliers_list', methods: ['GET'])]
    public function listSuppliers(): JsonResponse
    {
        $list = $this->em->getRepository(Supplier::class)->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC')
            ->getQuery()->getResult();

        $payload = array_map(fn($s) => ['id' => $s->getId(), 'name' => $s->getName(), 'contact' => $s->getContact(), 'address' => $s->getAddress(), 'fuelTypes' => $s->getFuelTypes()], $list);
        return $this->json($payload);
    }

    #[Route('/supplier/new', name: 'inventory_supplier_new', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function createSupplier(Request $request): JsonResponse
    {
        $data = $request->request->all();
        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $s = new Supplier();
        $s->setName($data['name']);
        $s->setContact($data['contact'] ?? null);
        $s->setAddress($data['address'] ?? null);
        $s->setFuelTypes($data['fuelTypes'] ?? null);

        $this->em->persist($s);
        $this->em->flush();

        return $this->json(['success' => true, 'id' => $s->getId()], Response::HTTP_CREATED);
    }

    #[Route('/supplier/{id}/invoice/new', name: 'inventory_supplier_invoice_new', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function createInvoice(Supplier $supplier, Request $request): JsonResponse
    {
        $data = $request->request->all();
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->json(['error' => 'Amount must be positive'], Response::HTTP_BAD_REQUEST);
        }

        $inv = new \App\Entity\Invoice();
        $inv->setSupplier($supplier);
        $inv->setAmount($amount);
        $inv->setStatus('pending');
        $inv->setReference($data['reference'] ?? null);

        $this->em->persist($inv);
        $this->em->flush();

        return $this->json(['success' => true, 'id' => $inv->getId()], Response::HTTP_CREATED);
    }

    #[Route('/supplier/{id}/delivery/new', name: 'inventory_supplier_delivery_new', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function createDelivery(Supplier $supplier, Request $request): JsonResponse
    {
        $data = $request->request->all();
        $scheduled = $data['scheduledAt'] ?? null;
        try {
            $dt = $scheduled ? new \DateTime($scheduled) : new \DateTime();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid scheduled date'], Response::HTTP_BAD_REQUEST);
        }

        $d = new \App\Entity\Delivery();
        $d->setSupplier($supplier);
        $d->setScheduledAt($dt);
        $d->setStatus('scheduled');
        $d->setAssignedDriver($data['assignedDriver'] ?? null);

        $this->em->persist($d);
        $this->em->flush();

        return $this->json(['success' => true, 'id' => $d->getId()], Response::HTTP_CREATED);
    }

    #[Route('/supplier/{id}/edit', name: 'inventory_supplier_edit', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function editSupplier(Supplier $supplier, Request $request): JsonResponse
    {
        $data = $request->request->all();
        $supplier->setName($data['name'] ?? $supplier->getName());
        $supplier->setContact($data['contact'] ?? $supplier->getContact());
        $supplier->setAddress($data['address'] ?? $supplier->getAddress());
        $supplier->setFuelTypes($data['fuelTypes'] ?? $supplier->getFuelTypes());
        $supplier->setUpdatedAt(new \DateTime());

        $this->em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/supplier/{id}/delete', name: 'inventory_supplier_delete', methods: ['POST','DELETE'])]
    #[IsGranted('ROLE_MANAGER')]
    public function deleteSupplier(Supplier $supplier): JsonResponse
    {
        $this->em->remove($supplier);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/suppliers/manage', name: 'inventory_suppliers_manage', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function manageSuppliers(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;

        $repo = $this->em->getRepository(Supplier::class);
        $qb = $repo->createQueryBuilder('s')->orderBy('s.name', 'ASC');

        $total = (int) $repo->createQueryBuilder('s')->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        $list = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        $last = (int) ceil($total / $perPage);

        // basic supplier metrics
        $activeDeliveries = (int) $this->em->getRepository(\App\Entity\Delivery::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status != :del')
            ->setParameter('del', 'delivered')
            ->getQuery()->getSingleScalarResult();

        $pendingInvoices = (int) $this->em->getRepository(\App\Entity\Invoice::class)
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.status = :pending')
            ->setParameter('pending', 'pending')
            ->getQuery()->getSingleScalarResult();

        return $this->render('inventory/suppliers.html.twig', [
            'suppliers' => $list,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last' => $last,
            'active_deliveries' => $activeDeliveries,
            'pending_invoices' => $pendingInvoices,
        ]);
    }

    #[Route('/supplier/{id}', name: 'inventory_supplier_view', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function viewSupplier(Supplier $supplier): Response
    {
        $invoices = $this->em->getRepository(\App\Entity\Invoice::class)
            ->createQueryBuilder('i')
            ->where('i.supplier = :s')
            ->setParameter('s', $supplier)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $deliveries = $this->em->getRepository(\App\Entity\Delivery::class)
            ->createQueryBuilder('d')
            ->where('d.supplier = :s')
            ->setParameter('s', $supplier)
            ->orderBy('d.scheduledAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        return $this->render('inventory/supplier-view.html.twig', [
            'supplier' => $supplier,
            'invoices' => $invoices,
            'deliveries' => $deliveries,
        ]);
    }

    #[\Route('/supplier/{supplierId}/invoice/{invoiceId}/pdf', name: 'inventory_supplier_invoice_pdf', methods: ['GET'])]
    #[\IsGranted('ROLE_SUB_ADMIN')]
    public function invoicePdf(int $supplierId, int $invoiceId): Response
    {
        $inv = $this->em->getRepository(\App\Entity\Invoice::class)->find($invoiceId);
        if (!$inv || $inv->getSupplier()->getId() !== $supplierId) {
            return new JsonResponse(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        // render invoice HTML
        $html = $this->renderView('inventory/invoice-pdf.html.twig', ['invoice' => $inv, 'supplier' => $inv->getSupplier()]);

        // generate PDF using Dompdf
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        $response = new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-' . $inv->getId() . '.pdf"'
        ]);

        return $response;
    }

    #[\Route('/delivery/{id}/update', name: 'inventory_delivery_update', methods: ['POST'])]
    #[\IsGranted('ROLE_SUB_ADMIN')]
    public function updateDeliveryStatus(\App\Entity\Delivery $delivery, Request $request): JsonResponse
    {
        $status = $request->request->get('status');
        $allowed = ['scheduled','approved','dispatched','in_transit','delivered','cancelled'];
        if (!in_array($status, $allowed, true)) {
            return $this->json(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $delivery->setStatus($status);
        if ($driver = $request->request->get('assignedDriver')) {
            $delivery->setAssignedDriver($driver);
        }
        $delivery->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json(['success' => true, 'status' => $delivery->getStatus()]);
    }
}
