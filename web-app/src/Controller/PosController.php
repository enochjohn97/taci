<?php
// src/Controller/PosController.php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\InventoryLog;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Service\LoyaltyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos')]
#[IsGranted('ROLE_STAFF')]
class PosController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoyaltyService $loyaltyService,
    ) {}

    #[Route('', name: 'app_pos_dashboard')]
    public function dashboard(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        
        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'pos_dashboard',
            'products' => $products,
        ]);
    }

    #[Route('/register', name: 'app_pos_register')]
    public function register(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        
        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'pos_register',
            'products' => $products,
        ]);
    }

    #[Route('/api/product/barcode/{barcode}', name: 'app_pos_barcode', methods: ['GET'])]
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

    #[Route('/api/product/{id}', name: 'app_pos_product', methods: ['GET'])]
    public function getProduct(Product $product): JsonResponse
    {
        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'barcode' => $product->getBarcode(),
            'unitPrice' => $product->getUnitPrice(),
            'stockQuantity' => $product->getStockQuantity(),
            'category' => $product->getCategory(),
        ]);
    }

    #[Route('/api/transaction/create', name: 'app_pos_create_transaction', methods: ['POST'])]
    public function createTransaction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['items'])) {
            return $this->json(['error' => 'Invalid data: items are required'], Response::HTTP_BAD_REQUEST);
        }
        $items = $data['items'];
        $paymentMethod = $data['paymentMethod'] ?? 'cash';
        $discountAmount = (float) ($data['discountAmount'] ?? 0);
        $loyaltyPointsUsed = (float) ($data['loyaltyPointsUsed'] ?? 0);
        $customerId = $data['customerId'] ?? null;

        $conn = $this->em->getConnection();
        $itemsCreated = [];

        try {
            $conn->beginTransaction();

            $sale = new Sale();
            $sale->setCashier($this->getUser());
            $sale->setPaymentMethod($paymentMethod);
            $sale->setDiscountAmount($discountAmount);
            $sale->setLoyaltyPointsUsed($loyaltyPointsUsed);

            $totalAmount = 0;

            // First, create sale and items (cascade persist will handle items when persisting sale)
            foreach ($items as $item) {
                $product = $this->em->getRepository(Product::class)->find($item['productId']);
                if (!$product) {
                    throw new \Exception('Product not found: ' . $item['productId']);
                }

                if ($product->getStockQuantity() < $item['quantity']) {
                    throw new \Exception('Insufficient stock for ' . $product->getName());
                }

                $saleItem = new SaleItem();
                $saleItem->setProduct($product);
                $saleItem->setQuantity($item['quantity']);
                $saleItem->setUnitPrice($product->getUnitPrice());
                $saleItem->calculateSubtotal();
                $sale->addItem($saleItem);

                $totalAmount += $saleItem->getSubtotal();

                $itemsCreated[] = [
                    'productId' => $product->getId(),
                    'name' => $product->getName(),
                    'quantity' => $item['quantity'],
                    'unitPrice' => $product->getUnitPrice(),
                    'subtotal' => $saleItem->getSubtotal(),
                ];
            }

            $sale->setTotalAmount($totalAmount - $discountAmount);
            $this->em->persist($sale);
            $this->em->flush(); // ensure sale id exists

            // Now deduct stock and create inventory logs referencing sale
            foreach ($sale->getItems() as $saleItem) {
                $product = $saleItem->getProduct();
                $qty = $saleItem->getQuantity();

                $oldStock = $product->getStockQuantity();
                $product->setStockQuantity($oldStock - $qty);
                $this->em->persist($product);

                $log = new InventoryLog();
                $log->setProduct($product);
                $log->setActionType('out');
                $log->setQuantityChanged($qty);
                $log->setStockBefore($oldStock);
                $log->setStockAfter($product->getStockQuantity());
                $log->setPerformedBy($this->getUser());
                $log->setReference('SALE#' . $sale->getId());
                $this->em->persist($log);

                if ($product->isLowStock()) {
                    $notif = new Notification();
                    $notif->setUser($this->getUser());
                    $notif->setType('low_stock');
                    $notif->setMessage('Product "' . $product->getName() . '" is running low (stock: ' . $product->getStockQuantity() . ')');
                    $notif->setLink('/inventory/products/' . $product->getId());
                    $this->em->persist($notif);
                }
            }

            // Create payment record
            $payment = new Payment();
            $payment->setSale($sale);
            $payment->setMethod($paymentMethod);
            $payment->setAmount($sale->getTotalAmount());
            $payment->setStatus('completed');
            $payment->markAsCompleted();
            $this->em->persist($payment);

            // Award loyalty points if customer
            if ($customerId) {
                $customer = $this->em->getRepository(\App\Entity\User::class)->find($customerId);
                if ($customer) {
                    $this->loyaltyService->awardPoints($customer, $totalAmount);
                }
            }

            $this->em->flush();
            $conn->commit();

            return $this->json([
                'success' => true,
                'saleId' => $sale->getId(),
                'totalAmount' => $sale->getTotalAmount(),
                'paymentMethod' => $paymentMethod,
                'items' => $itemsCreated,
                'timestamp' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            // Ensure exception does not leak sensitive info
            return $this->json(['error' => 'Transaction failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/transaction/{id}/receipt', name: 'app_pos_receipt', methods: ['GET'])]
    public function getReceipt(Sale $sale): JsonResponse
    {
        $items = [];
        foreach ($sale->getItems() as $saleItem) {
            $items[] = [
                'name' => $saleItem->getProduct()->getName(),
                'quantity' => $saleItem->getQuantity(),
                'unitPrice' => $saleItem->getUnitPrice(),
                'subtotal' => $saleItem->getSubtotal(),
            ];
        }

        return $this->json([
            'saleId' => $sale->getId(),
            'cashier' => $sale->getCashier()->getUsername(),
            'timestamp' => $sale->getCreatedAt()->format('Y-m-d H:i:s'),
            'items' => $items,
            'subtotal' => $sale->getTotalAmount() + $sale->getDiscountAmount(),
            'discount' => $sale->getDiscountAmount(),
            'total' => $sale->getTotalAmount(),
            'paymentMethod' => $sale->getPaymentMethod(),
            'status' => $sale->getStatus(),
        ]);
    }

    #[Route('/api/transaction/{id}/void', name: 'app_pos_void', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function voidTransaction(Sale $sale): JsonResponse
    {
        $sale->setStatus('voided');
        
        // Restore stock
        foreach ($sale->getItems() as $item) {
            $product = $item->getProduct();
            $oldStock = $product->getStockQuantity();
            $product->setStockQuantity($oldStock + $item->getQuantity());

            $log = new InventoryLog();
            $log->setProduct($product);
            $log->setActionType('in');
            $log->setQuantityChanged($item->getQuantity());
            $log->setStockBefore($oldStock);
            $log->setStockAfter($product->getStockQuantity());
            $log->setPerformedBy($this->getUser());
            $log->setReference('VOID#' . $sale->getId());
            $this->em->persist($log);
        }

        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Transaction voided']);
    }

    #[Route('/daily-summary', name: 'app_pos_daily_summary')]
    public function dailySummary(): Response
    {
        $today = new \DateTime();
        $today->setTime(0, 0);

        $sales = $this->em->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->where('s.createdAt >= :today')
            ->andWhere('s.status = :status')
            ->setParameter('today', $today)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getResult();

        $totalRevenue = array_sum(array_map(fn($s) => $s->getTotalAmount(), $sales));
        $totalTransactions = count($sales);
        $averageTransaction = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Group by payment method
        $byMethod = [];
        foreach ($sales as $sale) {
            $method = $sale->getPaymentMethod();
            if (!isset($byMethod[$method])) {
                $byMethod[$method] = ['count' => 0, 'amount' => 0];
            }
            $byMethod[$method]['count']++;
            $byMethod[$method]['amount'] += $sale->getTotalAmount();
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'pos_daily_summary',
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'average_transaction' => $averageTransaction,
            'by_method' => $byMethod,
            'sales' => $sales,
        ]);
    }
}
