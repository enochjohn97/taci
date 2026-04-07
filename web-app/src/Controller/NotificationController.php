<?php
// src/Controller/NotificationController.php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN|ROLE_STAFF')]
class NotificationController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'app_notifications')]
    public function index(): Response
    {
        $user = $this->getUser();
        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC'], 50);

        $unreadCount = $this->em->getRepository(Notification::class)
            ->count(['user' => $user, 'isRead' => false]);

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    #[Route('/unread-count', name: 'app_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->em->getRepository(Notification::class)
            ->count(['user' => $user, 'isRead' => false]);

        return $this->json(['unread_count' => $count]);
    }

    #[Route('/list', name: 'app_notifications_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC'], 10);

        $data = [];
        foreach ($notifications as $notif) {
            $data[] = [
                'id' => $notif->getId(),
                'type' => $notif->getType(),
                'message' => $notif->getMessage(),
                'link' => $notif->getLink(),
                'isRead' => $notif->isRead(),
                'createdAt' => $notif->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $notification->setIsRead(true);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        
        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        foreach ($notifications as $notif) {
            $notif->setIsRead(true);
        }

        $this->em->flush();

        return $this->json(['success' => true, 'marked' => count($notifications)]);
    }

    #[Route('/{id}/delete', name: 'app_notification_delete', methods: ['POST'])]
    public function delete(Notification $notification): JsonResponse
    {
        if ($notification->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $this->em->remove($notification);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/delete-all', name: 'app_notifications_delete_all', methods: ['POST'])]
    public function deleteAll(): JsonResponse
    {
        $user = $this->getUser();
        
        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user]);

        foreach ($notifications as $notif) {
            $this->em->remove($notif);
        }

        $this->em->flush();

        return $this->json(['success' => true, 'deleted' => count($notifications)]);
    }

    #[Route('/settings', name: 'app_notification_settings')]
    public function settings(): Response
    {
        // TODO: Implement notification preferences per user
        return $this->render('notifications/settings.html.twig');
    }
}
