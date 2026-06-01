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
    #[IsGranted('ROLE_SUB_ADMIN')]
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

    #[Route('/supplier/{id}/edit', name: 'inventory_supplier_edit', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
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
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function deleteSupplier(Supplier $supplier): JsonResponse
    {
        $this->em->remove($supplier);
        $this->em->flush();
        return $this->json(['success' => true]);
    }
}
