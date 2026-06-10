<?php
// src/Controller/PaystackWebhookController.php

namespace App\Controller;

use App\Service\PaymentService;
use App\Service\ReceiptService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaystackWebhookController extends AbstractController
{
    public function __construct(
        private PaymentService $paymentService,
        private ReceiptService $receiptService,
        private EntityManagerInterface $em,
        private \App\Service\NotificationService $notificationService,
    ) {}

    #[Route('/paystack/webhook', name: 'paystack_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $secret = $_ENV['PAYSTACK_SECRET_KEY'] ?? null;
        $signature = $request->headers->get('x-paystack-signature');
        $content = $request->getContent();

        if (!$secret || !$signature) {
            return new Response('Missing headers', Response::HTTP_BAD_REQUEST);
        }

        $hash = hash_hmac('sha512', $content, $secret);
        if (!hash_equals($hash, $signature)) {
            return new Response('Invalid signature', Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        // Paystack sends event with 'data' key
        $data = $payload['data'] ?? $payload;

        $handled = $this->paymentService->handlePaystackWebhook($data);

        if ($handled) {
            // find payment and sale
            $reference = $data['reference'] ?? null;
            $payment = $this->em->getRepository(\App\Entity\Payment::class)->findOneBy(['reference' => $reference]);
            if ($payment) {
                $sale = $payment->getSale();
                try {
                    $receiptUrl = $this->receiptService->generateSaleReceipt($sale);
                } catch (\Exception $e) {
                    error_log('Failed to generate receipt: ' . $e->getMessage());
                }

                // Notify all managers + admins about the completed payment in real-time
                try {
                    $this->notificationService->notifyPaymentReceived(
                        $sale->getCashier(),
                        $payment->getAmount()
                    );
                } catch (\Exception $e) {
                    error_log('Failed to send payment notification: ' . $e->getMessage());
                }
            }
            return new Response('Webhook processed', Response::HTTP_OK);
        }

        return new Response('Not handled', Response::HTTP_OK);
    }
}
