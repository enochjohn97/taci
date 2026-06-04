<?php
// src/Controller/MemoController.php

namespace App\Controller;

use App\Entity\Memo;
use App\Entity\MemoRecipient;
use App\Entity\MemoAttachment;
use App\Entity\User;
use App\Entity\UserRole;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/memos')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MemoController extends AbstractController
{
    private NotificationService $notificationService;

    public function __construct(private EntityManagerInterface $em, NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    #[Route('', name: 'app_memo_dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        
        $inbox = $this->em->getRepository(MemoRecipient::class)
            ->createQueryBuilder('mr')
            ->join('mr.memo', 'm')
            ->where('mr.recipient = :user OR (mr.recipientRole = :role AND mr.recipient IS NULL)')
            ->setParameter('user', $user)
            ->setParameter('role', $user->getRole()->value)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $sent = $this->em->getRepository(Memo::class)
            ->findBy(['sender' => $user, 'parentMemo' => null], ['createdAt' => 'DESC'], 20);

        $drafts = $this->em->getRepository(Memo::class)
            ->findBy(['sender' => $user, 'status' => 'draft'], ['createdAt' => 'DESC']);

        $unreadCount = $this->em->getRepository(MemoRecipient::class)
            ->count(['recipient' => $user, 'isRead' => false]);

        return $this->render('memo/dashboard.html.twig', [
            'inbox' => $inbox,
            'sent' => $sent,
            'drafts' => $drafts,
            'unread_count' => $unreadCount,
        ]);
    }

    #[Route('/new', name: 'app_memo_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            if (empty($data['subject']) || empty($data['body'])) {
                $this->addFlash('error', 'Subject and body are required');
                return $this->redirectToRoute('app_memo_new');
            }
            
            $status = $data['status'] ?? 'draft';
            $recipientIds = $data['recipientIds'] ?? [];
            $recipientRoles = $data['recipientRoles'] ?? [];
            
            if ($status === 'sent' && empty($recipientIds) && empty($recipientRoles)) {
                $this->addFlash('error', 'Please select at least one recipient before sending.');
                return $this->redirectToRoute('app_memo_new');
            }
            
            $memo = new Memo();
            $memo->setSender($this->getUser());
            $memo->setSubject($data['subject']);
            $memo->setBody($data['body']);
            $memo->setStatus($data['status'] ?? 'draft');

            $this->em->persist($memo);

            // Add recipients with role-aware restrictions
            $sender = $this->getUser();
            $allowedRecipientRoles = $this->getAllowedRecipientRoles($sender->getRole()->value);
            $recipientRoles = array_values(array_filter($recipientRoles, fn($r) => in_array($r, $allowedRecipientRoles, true)));

            foreach ($recipientIds as $recipientId) {
                $recipient = $this->em->getRepository(User::class)->find($recipientId);
                if ($recipient) {
                    if (!in_array($recipient->getRole()->value, $allowedRecipientRoles, true)) {
                        continue;
                    }
                    $memoRecipient = new MemoRecipient();
                    $memoRecipient->setMemo($memo);
                    $memoRecipient->setRecipient($recipient);
                    $this->em->persist($memoRecipient);

                    // Send notification
                    if ($memo->getStatus() === 'sent') {
                                            // Use NotificationService to persist and broadcast
                                            $this->notificationService->sendNotification(
                                                $recipient,
                                                'memo_received',
                                                'New memo from ' . $this->getUser()->getUsername() . ': ' . $memo->getSubject(),
                                                '/memos/' . $memo->getId()
                                            );
                                        }
                }
            }

            foreach ($recipientRoles as $role) {
                $memoRecipient = new MemoRecipient();
                $memoRecipient->setMemo($memo);
                $memoRecipient->setRecipientRole($role);
                $this->em->persist($memoRecipient);
            }

            // Handle file uploads
            $files = $request->files->get('attachments', []);
            foreach ($files as $file) {
                if ($file && $file->getSize() <= 10485760) { // 10MB limit
                    $filename = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/memos';
                    
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $file->move($uploadDir, $filename);

                    $attachment = new MemoAttachment();
                    $attachment->setMemo($memo);
                    $attachment->setFileName($file->getClientOriginalName());
                    $attachment->setFilePath('/uploads/memos/' . $filename);
                    $attachment->setFileType($file->getMimeType());
                    $attachment->setFileSize($file->getSize());
                    $this->em->persist($attachment);
                }
            }

            $this->em->flush();

            $this->addFlash('success', 'Memo ' . ($memo->getStatus() === 'sent' ? 'sent' : 'saved as draft'));
            return $this->redirectToRoute('app_memo_dashboard');
        }

        $users = $this->em->getRepository(User::class)->findActiveUsers();
        $allowedRecipientRoles = $this->getAllowedRecipientRoles($this->getUser()->getRole()->value);
        $users = array_values(array_filter($users, function (User $user) use ($allowedRecipientRoles) {
            if ($user === $this->getUser()) {
                return false;
            }
            return in_array($user->getRole()->value, $allowedRecipientRoles, true);
        }));

        return $this->render('memo/new.html.twig', [
            'users' => $users,
            'roles' => $allowedRecipientRoles,
        ]);
    }

    #[Route('/{id}', name: 'app_memo_view')]
    public function view(Memo $memo): Response
    {
        $user = $this->getUser();

        // Mark as read
        $recipient = $this->em->getRepository(MemoRecipient::class)
            ->findOneBy(['memo' => $memo, 'recipient' => $user]);
        
        if ($recipient && !$recipient->isRead()) {
            $recipient->setIsRead(true);
            $this->em->flush();
        }

        // Get replies
        $replies = $this->em->getRepository(Memo::class)
            ->findBy(['parentMemo' => $memo], ['createdAt' => 'ASC']);

        return $this->render('memo/view.html.twig', [
            'memo' => $memo,
            'replies' => $replies,
            'attachments' => $memo->getAttachments(),
            'can_forward' => $this->isGranted('forward_memo'),
        ]);
    }

    #[Route('/{id}/forward', name: 'app_memo_forward', methods: ['GET', 'POST'])]
    public function forwardMemo(Memo $memo, Request $request): Response
    {
        $this->denyAccessUnlessGranted('forward_memo');

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            if (empty($data['body'])) {
                $this->addFlash('error', 'Forward message is required');
                return $this->redirectToRoute('app_memo_forward', ['id' => $memo->getId()]);
            }

            $forward = new Memo();
            $forward->setSender($this->getUser());
            $forward->setSubject('FWD: ' . $memo->getSubject());
            $forward->setBody($data['body'] . "\n\n--- Forwarded message ---\n" . $memo->getBody());
            $forward->setStatus('sent');
            $forward->setParentMemo($memo);

            $this->em->persist($forward);

            $sender = $this->getUser();
            $allowedRecipientRoles = $this->getAllowedRecipientRoles($sender->getRole()->value);
            $recipientIds = $data['recipientIds'] ?? [];
            $recipientRoles = array_values(array_filter($data['recipientRoles'] ?? [], fn($r) => in_array($r, $allowedRecipientRoles, true)));

            foreach ($recipientIds as $recipientId) {
                $recipient = $this->em->getRepository(User::class)->find($recipientId);
                if ($recipient && in_array($recipient->getRole()->value, $allowedRecipientRoles, true)) {
                    $memoRecipient = new MemoRecipient();
                    $memoRecipient->setMemo($forward);
                    $memoRecipient->setRecipient($recipient);
                    $this->em->persist($memoRecipient);

                    // Use NotificationService to persist and broadcast
                    $this->notificationService->sendNotification(
                        $recipient,
                        'memo_received',
                        'Forwarded memo from ' . $sender->getUsername() . ': ' . $forward->getSubject(),
                        '/memos/' . $forward->getId()
                    );
                }
            }

            foreach ($recipientRoles as $role) {
                $memoRecipient = new MemoRecipient();
                $memoRecipient->setMemo($forward);
                $memoRecipient->setRecipientRole($role);
                $this->em->persist($memoRecipient);
            }

            $this->em->flush();
            $this->addFlash('success', 'Memo forwarded successfully');
            return $this->redirectToRoute('app_memo_view', ['id' => $forward->getId()]);
        }

        $users = $this->em->getRepository(User::class)->findActiveUsers();
        $allowedRecipientRoles = $this->getAllowedRecipientRoles($this->getUser()->getRole()->value);
        $users = array_values(array_filter($users, function (User $user) use ($allowedRecipientRoles) {
            return $user !== $this->getUser() && in_array($user->getRole()->value, $allowedRecipientRoles, true);
        }));

        return $this->render('memo/forward.html.twig', [
            'memo' => $memo,
            'users' => $users,
            'roles' => $allowedRecipientRoles,
        ]);
    }

    #[Route('/{id}/reply', name: 'app_memo_reply', methods: ['POST'])]
    public function reply(Memo $memo, Request $request): Response
    {
        $data = $request->request->all();

        $reply = new Memo();
        $reply->setSender($this->getUser());
        $reply->setSubject('RE: ' . $memo->getSubject());
        $reply->setBody($data['body']);
        $reply->setStatus('sent');
        $reply->setParentMemo($memo);

        $this->em->persist($reply);

        // Add original sender as recipient
        $memoRecipient = new MemoRecipient();
        $memoRecipient->setMemo($reply);
        $memoRecipient->setRecipient($memo->getSender());
        $this->em->persist($memoRecipient);

        // Notify original sender via NotificationService
        $this->notificationService->sendNotification(
            $memo->getSender(),
            'memo_received',
            'Reply from ' . $this->getUser()->getUsername(),
            '/memos/' . $memo->getId()
        );

        $this->em->flush();

        $this->addFlash('success', 'Reply sent');
        return $this->redirectToRoute('app_memo_view', ['id' => $memo->getId()]);
    }

    #[Route('/{id}/approve', name: 'app_memo_approve', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function approve(Memo $memo, Request $request): JsonResponse
    {
        $memo->setStatus('approved');
        $memo->setApprovalNotes($request->request->get('notes'));

        $this->em->flush();

        // Notify sender
        $this->notificationService->sendNotification(
            $memo->getSender(),
            'memo_approved',
            'Your memo "' . $memo->getSubject() . '" was approved',
            '/memos/' . $memo->getId()
        );

        return $this->json(['success' => true, 'message' => 'Memo approved']);
    }

    #[Route('/{id}/decline', name: 'app_memo_decline', methods: ['POST'])]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function decline(Memo $memo, Request $request): JsonResponse
    {
        $memo->setStatus('declined');
        $memo->setApprovalNotes($request->request->get('notes'));

        $this->em->flush();

        // Notify sender
        $this->notificationService->sendNotification(
            $memo->getSender(),
            'memo_declined',
            'Your memo "' . $memo->getSubject() . '" was declined',
            '/memos/' . $memo->getId()
        );

        return $this->json(['success' => true, 'message' => 'Memo declined']);
    }

    #[Route('/{id}/delete', name: 'app_memo_delete', methods: ['POST'])]
    public function delete(Memo $memo): Response
    {
        if ($memo->getSender() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $this->em->remove($memo);
        $this->em->flush();

        $this->addFlash('success', 'Memo deleted');
        return $this->redirectToRoute('app_memo_dashboard');
    }

    private function getAllowedRecipientRoles(string $senderRole): array
    {
        // Each role may address all other roles (sender's own role is always excluded at the user-filter level).
        return match ($senderRole) {
            UserRole::ROLE_MANAGER->value => [
                UserRole::ROLE_SUPER_ADMIN->value,
                UserRole::ROLE_SUB_ADMIN->value,
            ],
            UserRole::ROLE_SUB_ADMIN->value => [
                UserRole::ROLE_SUPER_ADMIN->value,
                UserRole::ROLE_MANAGER->value,
            ],
            UserRole::ROLE_SUPER_ADMIN->value => [
                UserRole::ROLE_SUB_ADMIN->value,
                UserRole::ROLE_MANAGER->value,
            ],
            default => [UserRole::ROLE_MANAGER->value],
        };
    }
}
