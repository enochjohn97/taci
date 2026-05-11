<?php
// src/Service/NotificationService.php

namespace App\Service;

use App\Entity\User;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * NotificationService - Handles in-app and real-time notifications via Mercure
 * 
 * This service manages:
 * - Database notifications for audit trails
 * - Real-time push via Mercure WebSocket server
 * - Bulk notifications
 * - Specialized notification types
 */
class NotificationService
{
    private HttpClientInterface $httpClient;
    private const MERCURE_TOPIC_PREFIX = 'notifications/';

    public function __construct(
        private EntityManagerInterface $em,
        private string $mercureUrl,
        private string $mercureJwtSecret,
    ) {
        $this->httpClient = HttpClient::create();
    }

    public function sendNotification(
        User $user,
        string $type,
        string $message,
        ?string $link = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setLink($link);

        $this->em->persist($notification);
        $this->em->flush();

        // Send real-time notification via Mercure
        $this->publishMercureNotification($user, $notification);

        return $notification;
    }

    public function sendBulkNotification(
        array $users,
        string $type,
        string $message,
        ?string $link = null
    ): array {
        $notifications = [];
        foreach ($users as $user) {
            $notifications[] = $this->sendNotification($user, $type, $message, $link);
        }
        return $notifications;
    }

    /**
     * Publish notification in real-time via Mercure
     */
    private function publishMercureNotification(User $user, Notification $notification): void
    {
        try {
            $userId = $user->getId();
            $topic = self::MERCURE_TOPIC_PREFIX . $userId;

            $payload = [
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'message' => $notification->getMessage(),
                'link' => $notification->getLink(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
                'isRead' => false,
            ];

            $jwt = $this->generateMercureJwt([$topic, self::MERCURE_TOPIC_PREFIX . 'all']);

            $this->httpClient->request('POST', $this->mercureUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'topic' => $topic,
                    'data' => json_encode($payload),
                ]),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the notification save
            error_log('[Mercure] Failed to publish notification: ' . $e->getMessage());
        }
    }

    /**
     * Generate JWT token for Mercure authentication
     */
    private function generateMercureJwt(array $topics = []): string
    {
        $payload = [
            'iss' => 'https://tacipetroleum.local',
            'sub' => 'taci_app',
            'mercure' => [
                'publish' => $topics,
            ],
        ];

        return JWT::encode(
            $payload,
            $this->mercureJwtSecret,
            'HS256'
        );
    }

    public function notifyLowStock(User $admin, string $productName): Notification
    {
        return $this->sendNotification(
            $admin,
            'low_stock',
            "Product '$productName' is running low on stock",
            '/inventory/products'
        );
    }

    public function notifyNewOrder(User $admin, int $orderId): Notification
    {
        return $this->sendNotification(
            $admin,
            'new_order',
            "New order #$orderId has been placed",
            "/orders/$orderId"
        );
    }

    public function notifyMemoReceived(User $recipient, string $senderName, string $subject): Notification
    {
        return $this->sendNotification(
            $recipient,
            'memo_received',
            "New memo from $senderName: $subject",
            '/memos/inbox'
        );
    }

    public function notifyPaymentReceived(User $admin, float $amount): Notification
    {
        return $this->sendNotification(
            $admin,
            'payment_received',
            "Payment of ₦" . number_format($amount, 2) . " received",
            '/sales'
        );
    }

    public function notifyInventoryRestocked(User $manager, string $productName, int $quantity): Notification
    {
        return $this->sendNotification(
            $manager,
            'inventory_restocked',
            "$productName has been restocked with $quantity units",
            '/inventory/products'
        );
    }

    public function notifyUserActivity(User $admin, string $username, string $action): Notification
    {
        return $this->sendNotification(
            $admin,
            'user_activity',
            "User '$username' performed: $action",
            '/settings/admin'
        );
    }

    public function getUserNotifications(User $user, int $limit = 10): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                $limit
            );
    }

    public function getUnreadNotifications(User $user): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);
    }

    public function markAsRead(Notification $notification): Notification
    {
        $notification->setIsRead(true);
        $this->em->persist($notification);
        $this->em->flush();

        // Optionally publish to Mercure that notification was read
        $this->publishMercureNotificationRead($notification);

        return $notification;
    }

    private function publishMercureNotificationRead(Notification $notification): void
    {
        try {
            $userId = $notification->getUser()->getId();
            $topic = self::MERCURE_TOPIC_PREFIX . $userId;

            $payload = [
                'id' => $notification->getId(),
                'action' => 'marked_read',
            ];

            $jwt = $this->generateMercureJwt([$topic]);

            $this->httpClient->request('POST', $this->mercureUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'topic' => $topic,
                    'data' => json_encode($payload),
                ]),
            ]);
        } catch (\Exception $e) {
            error_log('[Mercure] Failed to publish read status: ' . $e->getMessage());
        }
    }

    public function deleteNotification(Notification $notification): void
    {
        $this->em->remove($notification);
        $this->em->flush();
    }

    public function markAllAsRead(User $user): int
    {
        $notifications = $this->getUnreadNotifications($user);
        foreach ($notifications as $notification) {
            $this->markAsRead($notification);
        }
        return count($notifications);
    }
}
