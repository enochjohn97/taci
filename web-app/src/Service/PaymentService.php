<?php
// src/Service/PaymentService.php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Sale;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PaymentService - Handles payment processing via Paystack
 * 
 * This service manages:
 * - Payment initialization
 * - Transaction verification
 * - Webhook handling
 * - Refunds
 */
class PaymentService
{
    private HttpClientInterface $httpClient;
    private const PAYSTACK_API_URL = 'https://api.paystack.co';

    public function __construct(
        private EntityManagerInterface $em,
        private string $paystackPublicKey,
        private string $paystackSecretKey,
        private string $adminEmail,
        private \App\Service\NotificationService $notificationService,
        private \App\Service\ReceiptService $receiptService
    ) {
        $this->httpClient = HttpClient::create();
    }

    public function initializePaystackTransaction(Sale $sale, float $amount): array
    {
        $payment = new Payment();
        $payment->setSale($sale);
        $payment->setMethod('card');
        $payment->setAmount($amount);
        $payment->setStatus('pending');

        $this->em->persist($payment);
        $this->em->flush();

        // Get customer email from cashier (transaction handler)
        $customerEmail = $sale->getCashier()->getEmail();

        $payload = [
            'email' => $customerEmail,
            'amount' => (int) ($amount * 100), // Convert to kobo
            'reference' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', ($_ENV['PAYSTACK_BUSINESS_NAME'] ?? $_ENV['APP_NAME'] ?? 'APP'))) . '-' . $payment->getId() . '-' . time(),
            'metadata' => [
                'payment_id' => $payment->getId(),
                'sale_id' => $sale->getId(),
                // Use PAYSTACK_BUSINESS_NAME or APP_NAME environment variables
                'store' => ($_ENV['PAYSTACK_BUSINESS_NAME'] ?? $_ENV['APP_NAME']) ?? throw new \RuntimeException('PAYSTACK_BUSINESS_NAME or APP_NAME must be set in environment'),
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', self::PAYSTACK_API_URL . '/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if ($data['status'] ?? false) {
                $payment->setReference($data['data']['reference']);
                $payment->setTransactionId($data['data']['access_code']);
                $payment->setGatewayResponse(json_encode($data['data']));
                $this->em->flush();

                return [
                    'success' => true,
                    'authorizationUrl' => $data['data']['authorization_url'],
                    'accessCode' => $data['data']['access_code'],
                    'reference' => $data['data']['reference'],
                ];
            }
        } catch (\Exception $e) {
            $payment->setStatus('failed');
            $payment->setGatewayResponse($e->getMessage());
            $this->em->flush();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => false, 'error' => 'Failed to initialize payment'];
    }

    public function verifyPaystackTransaction(string $reference): bool
    {
        try {
            $response = $this->httpClient->request('GET', self::PAYSTACK_API_URL . '/transaction/verify/' . urlencode($reference), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                ],
            ]);

            $data = $response->toArray();

            if (($data['status'] ?? false) && ($data['data']['status'] ?? false) === 'success') {
                $this->markPaymentAsCompleted($reference, $data['data']);
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    private function markPaymentAsCompleted(string $reference, array $gatewayData = []): void
    {
        $payment = $this->em->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);

        if ($payment) {
            $payment->setStatus('completed');
            $payment->setCompletedAt(new \DateTime());
            $payment->setGatewayResponse(json_encode($gatewayData));
            $sale = $payment->getSale();
            $sale->setStatus('completed');

            // Deduct stock and create inventory logs
            foreach ($sale->getItems() as $saleItem) {
                $product = $saleItem->getProduct();
                $qty = $saleItem->getQuantity();

                $oldStock = $product->getStockQuantity();
                $product->setStockQuantity($oldStock - $qty);
                $this->em->persist($product);

                $log = new \App\Entity\InventoryLog();
                $log->setProduct($product);
                $log->setActionType('out');
                $log->setQuantityChanged($qty);
                $log->setStockBefore($oldStock);
                $log->setStockAfter($product->getStockQuantity());
                $log->setPerformedBy($sale->getCashier());
                $log->setReference('SALE#' . $sale->getId());
                $this->em->persist($log);

                if (method_exists($product, 'isLowStock') && $product->isLowStock()) {
                    // notify managers/admins via NotificationService
                    $admins = $this->em->getRepository(\App\Entity\User::class)->findBy(['roles' => ['ROLE_MANAGER']]);
                    foreach ($admins as $admin) {
                        $this->notificationService->notifyLowStock($admin, $product->getName());
                    }
                }
            }

            $this->em->flush();

            // Generate receipt PDF and notify cashier via NotificationService
            $receiptPath = $this->receiptService->generateSaleReceipt($sale);
            if ($sale->getCashier()) {
                $this->notificationService->sendNotification(
                    $sale->getCashier(),
                    'payment_received',
                    'Payment of ₦' . number_format($payment->getAmount(), 2) . ' received',
                    $receiptPath
                );
            }
        }
    }

    public function handlePaystackWebhook(array $data): bool
    {
        $reference = $data['reference'] ?? null;
        if (!$reference) {
            return false;
        }

        return $this->verifyPaystackTransaction($reference);
    }

    public function processRefund(Payment $payment, float $amount): bool
    {
        if ($payment->getMethod() === 'card') {
            // Implement refund logic based on payment gateway
            $payment->setStatus('refunded');
            $payment->getSale()->setStatus('refunded');
            $this->em->flush();
            return true;
        }

        return false;
    }
}
