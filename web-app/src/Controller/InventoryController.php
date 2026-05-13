<?php
// src/Controller/InventoryController.php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\InventoryLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventory')]
#[IsGranted('ROLE_MANAGER')]
class InventoryController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'app_inventory_dashboard')]
    public function dashboard(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        
        $lowStockProducts = array_filter($products, fn($p) => $p->isLowStock());
        $totalStockValue = array_sum(array_map(
            fn($p) => $p->getStockQuantity() * $p->getUnitPrice(),
            $products
        ));

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'inventory_dashboard',
            'total_products' => count($products),
            'low_stock_count' => count($lowStockProducts),
            'total_stock_value' => $totalStockValue,
            'products' => $products,
        ]);
    }

    #[Route('/products', name: 'app_inventory_products')]
    public function products(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $category = $request->query->get('category', '');

        $qb = $this->em->getRepository(Product::class)->createQueryBuilder('p');

        if ($search) {
            $qb->where('p.name LIKE :search OR p.barcode LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($category) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category);
        }

        $products = $qb->getQuery()->getResult();

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'inventory_products',
            'products' => $products,
            'search' => $search,
            'category' => $category,
        ]);
    }

    #[Route('/product/new', name: 'app_inventory_product_new', methods: ['GET', 'POST'])]
    public function newProduct(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            if (empty($data['name']) || empty($data['barcode']) || empty($data['unitPrice'])) {
                $this->addFlash('error', 'Product name, barcode, and unit price are required');
                return $this->redirectToRoute('app_inventory_product_new');
            }

            $product = new Product();
            $product->setName($data['name']);
            $product->setDescription($data['description'] ?? null);
            $product->setCategory($data['category']);
            $product->setBarcode($data['barcode']);
            $product->setUnitPrice((float) $data['unitPrice']);
            $product->setStockQuantity((int) $data['stockQuantity']);
            $product->setReorderLevel((int) $data['reorderLevel']);
            $product->setCostPrice((float) ($data['costPrice'] ?? 0));

            $this->em->persist($product);
            $this->em->flush();

            $this->addFlash('success', 'Product created successfully');
            return $this->redirectToRoute('app_inventory_products');
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'inventory_product_form',
            'edit' => false,
        ]);
    }

    #[Route('/product/{id}/edit', name: 'app_inventory_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(Product $product, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $product->setName($data['name']);
            $product->setDescription($data['description'] ?? null);
            $product->setCategory($data['category']);
            $product->setBarcode($data['barcode']);
            $product->setUnitPrice((float) $data['unitPrice']);
            $product->setReorderLevel((int) $data['reorderLevel']);
            $product->setCostPrice((float) ($data['costPrice'] ?? 0));

            $this->em->flush();

            $this->addFlash('success', 'Product updated successfully');
            return $this->redirectToRoute('app_inventory_products');
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'inventory_product_form',
            'product' => $product,
            'edit' => true,
        ]);
    }

    #[Route('/product/{id}/view', name: 'app_inventory_product_view')]
    public function viewProduct(Product $product): Response
    {
        $logs = $this->em->getRepository(InventoryLog::class)
            ->findBy(['product' => $product], ['timestamp' => 'DESC'], 20);

        return $this->render('inventory/product-view.html.twig', [
            'product' => $product,
            'logs' => $logs,
        ]);
    }

    #[Route('/product/{id}/adjust', name: 'app_inventory_adjust', methods: ['POST'])]
    public function adjustStock(Product $product, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $quantity = (int) $data['quantity'];
        $notes = $data['notes'] ?? null;

        $oldStock = $product->getStockQuantity();
        $newStock = $oldStock + $quantity;

        if ($newStock < 0) {
            return $this->json(['error' => 'Invalid quantity'], 400);
        }

        $product->setStockQuantity($newStock);

        $log = new InventoryLog();
        $log->setProduct($product);
        $log->setActionType($quantity > 0 ? 'in' : 'adjustment');
        $log->setQuantityChanged(abs($quantity));
        $log->setStockBefore($oldStock);
        $log->setStockAfter($newStock);
        $log->setPerformedBy($this->getUser());
        $log->setNotes($notes);

        $this->em->persist($log);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'oldStock' => $oldStock,
            'newStock' => $newStock,
        ]);
    }

    #[Route('/alerts', name: 'app_inventory_alerts')]
    public function lowStockAlerts(): Response
    {
        $products = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->where('p.stockQuantity <= p.reorderLevel')
            ->orderBy('p.stockQuantity', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('inventory/alerts.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/audit-trail', name: 'app_inventory_audit')]
    public function auditTrail(Request $request): Response
    {
        $productId = $request->query->get('product');
        
        $qb = $this->em->getRepository(InventoryLog::class)->createQueryBuilder('l');

        if ($productId) {
            $qb->where('l.product = :product')
               ->setParameter('product', $productId);
        }

        $logs = $qb->orderBy('l.timestamp', 'DESC')
                   ->setMaxResults(500)
                   ->getQuery()
                   ->getResult();

        return $this->render('inventory/audit-trail.html.twig', [
            'logs' => $logs,
            'product_id' => $productId,
        ]);
    }

    #[Route('/report/valuation', name: 'app_inventory_valuation')]
    public function valuationReport(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();

        $valuation = [];
        $totalValue = 0;

        foreach ($products as $product) {
            $itemValue = $product->getStockQuantity() * ($product->getCostPrice() ?? $product->getUnitPrice());
            $valuation[] = [
                'product' => $product,
                'quantity' => $product->getStockQuantity(),
                'unitCost' => $product->getCostPrice(),
                'itemValue' => $itemValue,
            ];
            $totalValue += $itemValue;
        }

        return $this->render('inventory/valuation-report.html.twig', [
            'valuation' => $valuation,
            'total_value' => $totalValue,
        ]);
    }
}
