<?php
// src/Controller/StoreController.php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\InventoryLog;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/store')]
#[IsGranted(expression: "is_granted('ROLE_STAFF') or is_granted('ROLE_MANAGER')")]
class StoreController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'app_store_dashboard')]
    public function dashboard(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        
        return $this->render('store/dashboard.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/receptionist', name: 'app_receptionist')]
    #[IsGranted('ROLE_STAFF')]
    public function receptionist(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        
        return $this->render('store/receptionist.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/api/products/barcode/{barcode}', name: 'app_product_by_barcode', methods: ['GET'])]
    public function getProductByBarcode(string $barcode): JsonResponse
    {
        $product = $this->em->getRepository(Product::class)->findOneBy(['barcode' => $barcode]);
        
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'barcode' => $product->getBarcode(),
            'unitPrice' => $product->getUnitPrice(),
            'stockQuantity' => $product->getStockQuantity(),
            'category' => $product->getCategory(),
        ]);
    }

    #[Route('/checkout', name: 'app_store_checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $items = $data['items'] ?? [];
        $paymentMethod = $data['paymentMethod'] ?? 'cash';
        $discountAmount = $data['discountAmount'] ?? 0;
        $loyaltyPointsUsed = $data['loyaltyPointsUsed'] ?? 0;

        $sale = new Sale();
        $sale->setCashier($this->getUser());
        $sale->setPaymentMethod($paymentMethod);
        $sale->setDiscountAmount($discountAmount);
        $sale->setLoyaltyPointsUsed($loyaltyPointsUsed);

        $totalAmount = 0;

        foreach ($items as $item) {
            $product = $this->em->getRepository(Product::class)->find($item['productId']);
            if (!$product) {
                return $this->json(['error' => 'Product not found'], Response::HTTP_BAD_REQUEST);
            }

            $saleItem = new SaleItem();
            $saleItem->setProduct($product);
            $saleItem->setQuantity($item['quantity']);
            $saleItem->setUnitPrice($product->getUnitPrice());
            $saleItem->calculateSubtotal();
            $sale->addItem($saleItem);

            $totalAmount += $saleItem->getSubtotal();

            // Deduct from stock
            $product->setStockQuantity($product->getStockQuantity() - $item['quantity']);
            
            // Log inventory change
            $log = new InventoryLog();
            $log->setProduct($product);
            $log->setActionType('out');
            $log->setQuantityChanged($item['quantity']);
            $log->setStockBefore($product->getStockQuantity() + $item['quantity']);
            $log->setStockAfter($product->getStockQuantity());
            $log->setPerformedBy($this->getUser());
            $log->setReference('SALE#' . uniqid());
            $this->em->persist($log);

            // Check for low stock
            if ($product->isLowStock()) {
                $notif = new Notification();
                $notif->setUser($this->getUser());
                $notif->setType('low_stock');
                $notif->setMessage('Product ' . $product->getName() . ' is running low on stock');
                $notif->setLink('/inventory/products/' . $product->getId());
                $this->em->persist($notif);
            }
        }

        $sale->setTotalAmount($totalAmount);
        $this->em->persist($sale);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'saleId' => $sale->getId(),
            'totalAmount' => $totalAmount,
        ]);
    }
}
