<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\InventoryLog;
use App\Entity\Transaction;
use App\Service\NotificationService;
use App\Service\ReceiptService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffPosController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private NotificationService $notificationService,
        private ReceiptService $receiptService,
        private string $paystackPublicKey,
        private string $paystackSecretKey,
    ) {}

    #[Route('/pos', name: 'staff_reception')]
    public function pos(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $products = $this->searchProducts($search);

        return $this->render('pos/staff_reception.html.twig', [
            'products' => $products,
            'paystack_public_key' => $this->paystackPublicKey,
            'initial_search' => $search,
        ]);
    }

    #[Route('/pos/products', name: 'staff_reception_products', methods: ['GET'])]
    public function products(Request $request): JsonResponse
    {
        $search = trim((string) $request->query->get('q', ''));
        $products = $this->searchProducts($search);

        $payload = array_map(static function (Product $product): array {
            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getUnitPrice(),
                'stock' => $product->getStockQuantity(),
                'imagePath' => $product->getImagePath(),
            ];
        }, $products);

        return $this->json($payload);
    }

    #[Route('/pos/checkout/initialize', name: 'staff_reception_checkout_initialize', methods: ['POST'])]
    public function initializeCheckout(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
            return $this->json(['success' => false, 'error' => 'Cart items are required.'], Response::HTTP_BAD_REQUEST);
        }

        $paymentMethod = strtolower((string) ($data['paymentMethod'] ?? 'cash'));
        if (!in_array($paymentMethod, ['cash', 'card', 'transfer'], true)) {
            return $this->json(['success' => false, 'error' => 'Unsupported payment method.'], Response::HTTP_BAD_REQUEST);
        }

        $validatedItems = [];
        $subtotal = 0.0;
        foreach ($data['items'] as $item) {
            $productId = (int) ($item['productId'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId < 1 || $quantity < 1) {
                return $this->json(['success' => false, 'error' => 'Invalid cart item.'], Response::HTTP_BAD_REQUEST);
            }

            $product = $this->em->getRepository(Product::class)->find($productId);
            if (!$product) {
                return $this->json(['success' => false, 'error' => 'Product not found.'], Response::HTTP_NOT_FOUND);
            }

            $lineTotal = $product->getUnitPrice() * $quantity;
            $subtotal += $lineTotal;
            $validatedItems[] = [
                'productId' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getUnitPrice(),
                'quantity' => $quantity,
                'lineTotal' => $lineTotal,
            ];
        }

        $discount = max(0.0, (float) ($data['discount'] ?? 0));
        $tax = $subtotal * 0.12;
        $total = max(0.0, $subtotal + $tax - $discount);

        /** @var \App\Entity\User $staff */
        $staff = $this->getUser();

        $transaction = new Transaction();
        $transaction->setStaffUser($staff);
        $transaction->setItems($validatedItems);
        $transaction->setSubtotal($subtotal);
        $transaction->setTax($tax);
        $transaction->setDiscount($discount);
        $transaction->setTotal($total);
        $transaction->setPaymentMethod($paymentMethod);
        $transaction->setStatus($paymentMethod === 'cash' ? 'completed' : 'pending');

        $this->em->persist($transaction);
        $this->em->flush();

        // Cash checkout is completed immediately; POS/Paystack is only used for card or transfer.
        if ($paymentMethod === 'cash') {
            $this->applyInventoryDeduction($transaction);
            $receiptUrl = $this->receiptService->generateTransactionReceipt($transaction);

            return $this->json([
                'success' => true,
                'transactionId' => $transaction->getId(),
                'status' => $transaction->getStatus(),
                'receiptUrl' => $receiptUrl,
                'paymentMethod' => $paymentMethod,
            ]);
        }

        $reference = sprintf('TACI-TX-%d-%d', $transaction->getId(), time());
        $transaction->setReference($reference);
        $this->em->flush();

        try {
            $response = $this->httpClient->request('POST', 'https://api.paystack.co/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email' => $staff->getEmail(),
                    'amount' => (int) round($total * 100),
                    'reference' => $reference,
                    'callback_url' => $this->generateUrl('staff_reception', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'channels' => $paymentMethod === 'transfer' ? ['bank_transfer'] : ['card'],
                    'metadata' => [
                        'transaction_id' => $transaction->getId(),
                        'staff_user_id' => $staff->getId(),
                        'payment_method' => $paymentMethod,
                    ],
                ],
            ])->toArray(false);
        } catch (\Throwable $e) {
            $transaction->setStatus('failed');
            $this->em->flush();
            return $this->json(['success' => false, 'error' => 'Could not initialize payment.'], Response::HTTP_BAD_GATEWAY);
        }

        if (!(bool) ($response['status'] ?? false)) {
            $transaction->setStatus('failed');
            $this->em->flush();
            return $this->json(['success' => false, 'error' => $response['message'] ?? 'Payment initialization failed.'], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'success' => true,
            'transactionId' => $transaction->getId(),
            'reference' => $reference,
            'authorizationUrl' => $response['data']['authorization_url'] ?? null,
            'accessCode' => $response['data']['access_code'] ?? null,
            'amount' => $total,
            'paymentMethod' => $paymentMethod,
        ]);
    }

    #[Route('/pos/checkout/verify/{reference}', name: 'staff_reception_checkout_verify', methods: ['POST'])]
    public function verifyCheckout(string $reference): JsonResponse
    {
        /** @var \App\Entity\User $staff */
        $staff = $this->getUser();
        $transaction = $this->em->getRepository(Transaction::class)->findOneBy([
            'reference' => $reference,
            'staffUser' => $staff,
        ]);

        if (!$transaction) {
            return $this->json(['success' => false, 'error' => 'Transaction not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($transaction->getStatus() === 'completed') {
            return $this->json([
                'success' => true,
                'transactionId' => $transaction->getId(),
                'receiptUrl' => $transaction->getReceiptUrl(),
                'status' => $transaction->getStatus(),
            ]);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.paystack.co/transaction/verify/' . urlencode($reference), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                ],
            ])->toArray(false);
        } catch (\Throwable) {
            return $this->json(['success' => false, 'error' => 'Could not verify payment.'], Response::HTTP_BAD_GATEWAY);
        }

        $status = $response['data']['status'] ?? null;
        if (($response['status'] ?? false) !== true || $status !== 'success') {
            $transaction->setStatus('failed');
            $this->em->flush();
            return $this->json(['success' => false, 'error' => 'Payment not completed.'], Response::HTTP_BAD_REQUEST);
        }

        $transaction->setStatus('completed');
        $this->applyInventoryDeduction($transaction);

        $receiptUrl = $this->receiptService->generateTransactionReceipt($transaction);
        $this->notificationService->notifyTransferCompleted($transaction->getTotal(), $staff->getUsername());

        return $this->json([
            'success' => true,
            'transactionId' => $transaction->getId(),
            'receiptUrl' => $receiptUrl,
            'status' => $transaction->getStatus(),
        ]);
    }

    private function searchProducts(string $search): array
    {
        $qb = $this->em->getRepository(Product::class)->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    private function applyInventoryDeduction(Transaction $transaction): void
    {
        foreach ($transaction->getItems() as $item) {
            $product = $this->em->getRepository(Product::class)->find((int) ($item['productId'] ?? 0));
            if (!$product) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                continue;
            }

            $stockBefore = $product->getStockQuantity();
            $stockAfter = max(0, $stockBefore - $quantity);
            $product->setStockQuantity($stockAfter);
            $this->em->persist($product);

            $log = new InventoryLog();
            $log->setProduct($product);
            $log->setActionType('out');
            $log->setQuantityChanged($quantity);
            $log->setStockBefore($stockBefore);
            $log->setStockAfter($stockAfter);
            $log->setPerformedBy($transaction->getStaffUser());
            $log->setReference('TX#' . $transaction->getId());
            $this->em->persist($log);
        }

        $this->em->flush();
    }
}
