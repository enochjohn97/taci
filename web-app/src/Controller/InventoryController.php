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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventory')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
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

        $suppliers = $this->em->getRepository(\App\Entity\Supplier::class)->findAll();
        return $this->render('inventory/dashboard.html.twig', [
            'total_products' => count($products),
            'low_stock_count' => count($lowStockProducts),
            'total_stock_value' => $totalStockValue,
            'products' => $products,
            'suppliers' => $suppliers,
        ]);
    }

    #[Route('/products', name: 'app_inventory_products')]
    public function products(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $category = $request->query->get('category', '');
        $sortBy = $request->query->get('sortBy', 'name');
        $sortDir = strtoupper((string) $request->query->get('sortDir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->em->getRepository(Product::class)->createQueryBuilder('p');

        if ($search) {
            $qb->andWhere('p.name LIKE :search OR p.barcode LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($category) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category);
        }

        $allowedSortColumns = ['name', 'category', 'unitPrice', 'stockQuantity', 'barcode'];
        if (!in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'name';
        }
        $products = $qb->orderBy('p.' . $sortBy, $sortDir)->getQuery()->getResult();

        $suppliers = $this->em->getRepository(\App\Entity\Supplier::class)->findAll();
        return $this->render('inventory/products.html.twig', [
            'products' => $products,
            'search' => $search,
            'category' => $category,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'suppliers' => $suppliers,
        ]);
    }

    #[Route('/product/new', name: 'app_inventory_product_new', methods: ['GET', 'POST'])]
    public function newProduct(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

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

            /** @var UploadedFile|null $image */
            $image = $request->files->get('image');
            if ($image) {
                // Server-side validation
                $maxBytes = 5 * 1024 * 1024; // 5MB
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $size = $image->getSize() ?? 0;
                $mime = $image->getMimeType();

                if ($size > $maxBytes) {
                    $this->addFlash('error', 'Image exceeds maximum size of 5MB');
                    return $this->redirectToRoute('app_inventory_product_new');
                }

                if (!in_array($mime, $allowed, true)) {
                    $this->addFlash('error', 'Invalid image type. Only JPEG, PNG and WEBP allowed');
                    return $this->redirectToRoute('app_inventory_product_new');
                }

                $extension = $image->guessExtension() ?: 'jpg';
                $filename = bin2hex(random_bytes(12)) . '.' . $extension;
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                try {
                    $image->move($uploadDir, $filename);
                    $product->setImagePath('/uploads/products/' . $filename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to save uploaded image');
                    return $this->redirectToRoute('app_inventory_product_new');
                }
            }

            $this->em->persist($product);
            $this->em->flush();

            $this->addFlash('success', 'Product created successfully');
            return $this->redirectToRoute('app_inventory_products');
        }

        $suppliers = $this->em->getRepository(\App\Entity\Supplier::class)->findAll();
        return $this->render('inventory/product_form.html.twig', [
            'edit' => false,
            'suppliers' => $suppliers,
        ]);
    }

    #[Route('/product/{id}/edit', name: 'app_inventory_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(Product $product, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $product->setName($data['name']);
            $product->setDescription($data['description'] ?? null);
            $product->setCategory($data['category']);
            $product->setBarcode($data['barcode']);
            $product->setUnitPrice((float) $data['unitPrice']);
            $product->setReorderLevel((int) $data['reorderLevel']);
            $product->setCostPrice((float) ($data['costPrice'] ?? 0));

            /** @var UploadedFile|null $image */
            $image = $request->files->get('image');
            if ($image) {
                // Server-side validation
                $maxBytes = 5 * 1024 * 1024; // 5MB
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $size = $image->getSize() ?? 0;
                $mime = $image->getMimeType();

                if ($size > $maxBytes) {
                    $this->addFlash('error', 'Image exceeds maximum size of 5MB');
                    return $this->redirectToRoute('app_inventory_product_edit', ['id' => $product->getId()]);
                }

                if (!in_array($mime, $allowed, true)) {
                    $this->addFlash('error', 'Invalid image type. Only JPEG, PNG and WEBP allowed');
                    return $this->redirectToRoute('app_inventory_product_edit', ['id' => $product->getId()]);
                }

                $extension = $image->guessExtension() ?: 'jpg';
                $filename = bin2hex(random_bytes(12)) . '.' . $extension;
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                try {
                    $image->move($uploadDir, $filename);
                    $product->setImagePath('/uploads/products/' . $filename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to save uploaded image');
                    return $this->redirectToRoute('app_inventory_product_edit', ['id' => $product->getId()]);
                }
            }

            $this->em->flush();

            $this->addFlash('success', 'Product updated successfully');
            return $this->redirectToRoute('app_inventory_products');
        }

        $suppliers = $this->em->getRepository(\App\Entity\Supplier::class)->findAll();
        return $this->render('inventory/product_form.html.twig', [
            'product' => $product,
            'edit' => true,
            'suppliers' => $suppliers,
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
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

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
