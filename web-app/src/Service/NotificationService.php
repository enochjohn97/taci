<?php
// src/Service/NotificationService.php

namespace App\Service;

use App\Entity\User;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em) {}

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

        // TODO: Trigger Mercure or WebSocket event for real-time delivery
        // TODO: Send FCM push notification if user has device tokens

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
        return $notification;
    }

    public function deleteNotification(Notification $notification): void
    {
        $this->em->remove($notification);
        $this->em->flush();
    }
}
