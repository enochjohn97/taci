<?php
// src/Service/PaymentService.php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Sale;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PaymentService
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private EntityManagerInterface $em,
        private string $paystackPublicKey,
        private string $paystackSecretKey,
        private string $flutterwavePublicKey,
        private string $flutterwaveSecretKey,
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

        $payload = [
            'email' => 'customer@example.com', // TODO: Get from customer entity
            'amount' => (int) ($amount * 100), // Convert to kobo
            'reference' => 'TXN-' . $payment->getId() . '-' . time(),
            'metadata' => [
                'payment_id' => $payment->getId(),
                'sale_id' => $sale->getId(),
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://api.paystack.co/transaction/initialize', [
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
                $this->em->flush();

                return [
                    'success' => true,
                    'authorizationUrl' => $data['data']['authorization_url'],
                    'accessCode' => $data['data']['access_code'],
                    'reference' => $data['data']['reference'],
                ];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => false, 'error' => 'Failed to initialize payment'];
    }

    public function verifyPaystackTransaction(string $reference): bool
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.paystack.co/transaction/verify/' . $reference, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                ],
            ]);

            $data = $response->toArray();

            if (($data['status'] ?? false) && ($data['data']['status'] ?? false) === 'success') {
                $this->markPaymentAsCompleted($reference);
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    public function initializeFlutterwaveTransaction(Sale $sale, float $amount): array
    {
        $payment = new Payment();
        $payment->setSale($sale);
        $payment->setMethod('card');
        $payment->setAmount($amount);
        $payment->setStatus('pending');

        $this->em->persist($payment);
        $this->em->flush();

        $payload = [
            'public_key' => $this->flutterwavePublicKey,
            'tx_ref' => 'TXN-' . $payment->getId() . '-' . time(),
            'amount' => $amount,
            'currency' => 'NGN',
            'payment_options' => 'card,ussd,bank_account',
            'customer' => [
                'email' => 'customer@example.com',
                'phone_number' => '08000000000',
                'name' => 'Customer',
            ],
            'customizations' => [
                'title' => 'TACI Petroleum',
                'logo' => 'https://tacipetroleum.com/logo.png',
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://api.flutterwave.com/v3/payments', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->flutterwaveSecretKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if ($data['status'] === 'success') {
                $payment->setReference($data['data']['link']);
                $payment->setTransactionId($data['data']['id']);
                $this->em->flush();

                return [
                    'success' => true,
                    'link' => $data['data']['link'],
                    'reference' => $data['data']['flw_ref'],
                ];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => false, 'error' => 'Failed to initialize payment'];
    }

    public function verifyFlutterwaveTransaction(string $transactionId): bool
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.flutterwave.com/v3/transactions/' . $transactionId . '/verify', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->flutterwaveSecretKey,
                ],
            ]);

            $data = $response->toArray();

            if ($data['status'] === 'success' && $data['data']['status'] === 'successful') {
                $this->markPaymentAsCompleted($data['data']['flw_ref']);
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    private function markPaymentAsCompleted(string $reference): void
    {
        $payment = $this->em->getRepository(Payment::class)
            ->findOneBy(['reference' => $reference]);

        if ($payment) {
            $payment->markAsCompleted();
            $payment->getSale()->setStatus('completed');

            // Notify admin
            $notif = new Notification();
            $notif->setUser($payment->getSale()->getCashier());
            $notif->setType('payment_received');
            $notif->setMessage('Payment of ₦' . number_format($payment->getAmount(), 2) . ' received');
            $notif->setLink('/sales/' . $payment->getSale()->getId());
            $this->em->persist($notif);

            $this->em->flush();
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

    public function handleFlutterwaveWebhook(array $data): bool
    {
        $transactionId = $data['id'] ?? null;
        if (!$transactionId) {
            return false;
        }

        return $this->verifyFlutterwaveTransaction($transactionId);
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
