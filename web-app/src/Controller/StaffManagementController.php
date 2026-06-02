<?php

namespace App\Controller;

use App\Entity\LeaveRequest;
use App\Entity\Shift;
use App\Entity\ShiftHandover;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_MANAGER')] // Inherited by SUPER_ADMIN and SUB_ADMIN
class StaffManagementController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/dashboard', name: 'app_staff_dashboard')]
    public function dashboard(): Response
    {
        // Fetch real data
        $staffCount = $this->em->getRepository(User::class)->count(['role' => UserRole::ROLE_STAFF->value]);
        
        $leaveReqRepo = $this->em->getRepository(LeaveRequest::class);
        $pendingLeaves = $leaveReqRepo->count(['status' => 'Pending']);
        
        // Attendance could be mocked or queried (e.g. users logged in today). We'll set a basic placeholder based on active staff for now
        $attendance = $staffCount; // Placeholder for real attendance logic
        
        $shiftRepo = $this->em->getRepository(Shift::class);
        $currentShift = $shiftRepo->findOneBy(['isActive' => true]);
        
        $shiftNotes = $this->em->getRepository(ShiftHandover::class)->findBy([], ['createdAt' => 'DESC'], 5);
        $formattedNotes = [];
        foreach ($shiftNotes as $note) {
            $formattedNotes[] = [
                'sender' => $note->getSender()->getRole()->value === 'ROLE_STAFF' ? 'staff' : 'admin',
                'name' => $note->getSender()->getUsername(),
                'msg' => $note->getMessage(),
                'time' => $note->getCreatedAt()->format('H:i')
            ];
        }

        $taskRepo = $this->em->getRepository(Task::class);
        $tasks = $taskRepo->findBy(['type' => 'task'], ['createdAt' => 'DESC']);
        $safetyChecks = $taskRepo->findBy(['type' => 'safety_checklist'], ['createdAt' => 'DESC']);

        $formattedTasks = array_map(fn($t) => ['id' => $t->getId(), 'label' => $t->getLabel(), 'done' => $t->isDone()], $tasks);
        $formattedChecks = array_map(fn($t) => ['id' => $t->getId(), 'label' => $t->getLabel(), 'ok' => $t->isDone()], $safetyChecks);

        return $this->render('staff/dashboard.html.twig', [
            'view_mode' => 'staff_dashboard',
            'staff_count' => $staffCount,
            'leave_requests_count' => $pendingLeaves,
            'attendance_count' => $attendance,
            'staff_on_duty' => $attendance,
            'current_shift' => $currentShift ? $currentShift->getName() . ' — Started ' . $currentShift->getStartTime()->format('H:i') : 'No Active Shift',
            'shift_notes' => $formattedNotes,
            'task_list' => $formattedTasks,
            'safety_checklist' => $formattedChecks,
        ]);
    }

    #[Route('/directory', name: 'app_staff_directory')]
    public function directory(): Response
    {
        return $this->render('staff/directory.html.twig', [
            'view_mode' => 'staff_directory',
        ]);
    }

    #[Route('/shifts', name: 'app_staff_shifts')]
    public function shifts(): Response
    {
        return $this->render('staff/shifts.html.twig', [
            'view_mode' => 'staff_shifts',
        ]);
    }

    #[Route('/leave-requests', name: 'app_staff_leave_requests')]
    public function leaveRequests(): Response
    {
        $repo = $this->em->getRepository(LeaveRequest::class);
        $allLeaves = $repo->findBy([], ['createdAt' => 'DESC']);
        $pendingCount = $repo->count(['status' => 'Pending']);
        
        $now = new \DateTime();
        $startOfMonth = (clone $now)->modify('first day of this month')->setTime(0,0,0);
        
        $qb = $repo->createQueryBuilder('l');
        $qb->select('count(l.id)')
           ->where('l.status = :status')
           ->andWhere('l.createdAt >= :start')
           ->setParameter('status', 'Approved')
           ->setParameter('start', $startOfMonth);
        $approvedThisMonth = $qb->getQuery()->getSingleScalarResult();

        $qb2 = $repo->createQueryBuilder('l');
        $qb2->select('count(l.id)')
           ->where('l.status = :status')
           ->andWhere('l.startDate <= :now')
           ->andWhere('l.endDate >= :now')
           ->setParameter('status', 'Approved')
           ->setParameter('now', clone $now);
        $onLeaveNow = $qb2->getQuery()->getSingleScalarResult();

        $staffs = $this->em->getRepository(User::class)->findBy(['role' => UserRole::ROLE_STAFF->value]);

        $formattedLeaves = [];
        foreach ($allLeaves as $leave) {
            $formattedLeaves[] = [
                'id' => $leave->getId(),
                'staff' => $leave->getStaff()->getUsername(),
                'type' => $leave->getType(),
                'duration' => $leave->getDuration(),
                'reason' => $leave->getReason(),
                'status' => $leave->getStatus(),
                'date' => $leave->getCreatedAt()->format('Y-m-d')
            ];
        }

        return $this->render('staff/leave_requests.html.twig', [
            'view_mode' => 'staff_leave_requests',
            'leaves' => $formattedLeaves,
            'pending_requests' => $pendingCount,
            'approved_leaves' => $approvedThisMonth,
            'on_leave_now' => $onLeaveNow,
            'staffs' => $staffs,
        ]);
    }

    #[Route('/leave-requests/{id}/action', name: 'app_staff_leave_action', methods: ['POST'])]
    public function leaveRequestAction(int $id, Request $request): JsonResponse
    {
        // Only SuperAdmin or SubAdmin can approve/decline
        if (!$this->isGranted('ROLE_SUB_ADMIN')) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $leave = $this->em->getRepository(LeaveRequest::class)->find($id);
        if (!$leave) {
            return new JsonResponse(['success' => false, 'error' => 'Leave request not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;

        if ($action === 'approve') {
            $leave->setStatus('Approved');
        } elseif ($action === 'decline') {
            $leave->setStatus('Rejected');
        } else {
            return new JsonResponse(['success' => false, 'error' => 'Invalid action'], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'status' => $leave->getStatus()]);
    }

    #[Route('/attendance', name: 'app_staff_attendance')]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function attendance(): Response
    {
        return $this->render('staff/attendance.html.twig', [
            'view_mode' => 'staff_attendance',
        ]);
    }

    #[Route('/payroll', name: 'app_staff_payroll')]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function payroll(): Response
    {
        return $this->render('staff/payroll.html.twig', [
            'view_mode' => 'staff_payroll',
        ]);
    }
}
