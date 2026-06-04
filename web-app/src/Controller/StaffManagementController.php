<?php
// src/Controller/StaffManagementController.php

namespace App\Controller;

use App\Entity\LeaveRequest;
use App\Entity\Shift;
use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager/staff', name: 'app_manager_staff_')]
#[IsGranted('ROLE_MANAGER')]
class StaffManagementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/leave-requests', name: 'leave_requests', methods: ['GET'])]
    public function leaveRequests(): Response
    {
        $requests = $this->em->getRepository(LeaveRequest::class)->findBy([], ['createdAt' => 'DESC']);
        return $this->render('staff/leave_requests.html.twig', [
            'leave_requests' => $requests,
        ]);
    }

    #[Route('/leave-requests/{id}/approve', name: 'approve_leave_request', methods: ['POST'])]
    public function approveLeaveRequest(LeaveRequest $leaveRequest, Request $request): Response
    {
        $status = $request->request->get('status');
        if (in_array($status, ['Approved', 'Rejected'], true)) {
            $leaveRequest->setStatus($status);
            $this->em->flush();
            $this->addFlash('success', 'Leave request status updated.');
        } else {
            $this->addFlash('error', 'Invalid status.');
        }

        return $this->redirectToRoute('app_manager_staff_leave_requests');
    }

    #[Route('/shifts', name: 'shifts', methods: ['GET', 'POST'])]
    public function shifts(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $startTime = new \DateTime($request->request->get('start_time'));
            $endTime = new \DateTime($request->request->get('end_time'));

            $shift = new Shift();
            $shift->setName($name);
            $shift->setStartTime($startTime);
            $shift->setEndTime($endTime);
            $shift->setIsActive(true);

            $this->em->persist($shift);
            $this->em->flush();

            $this->addFlash('success', 'Shift created successfully.');
            return $this->redirectToRoute('app_manager_staff_shifts');
        }

        $shifts = $this->em->getRepository(Shift::class)->findBy([], ['createdAt' => 'DESC']);
        return $this->render('staff/shifts.html.twig', [
            'shifts' => $shifts,
        ]);
    }
}
