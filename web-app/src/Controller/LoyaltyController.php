<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\LoyaltyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Legacy /loyalty routes — redirect or delegate to Customer Rewards Hub.
 * Each redirect mirrors the target route's role requirements (no auth bypass via redirect).
 */
#[Route('/loyalty')]
class LoyaltyController extends AbstractController
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    #[Route('', name: 'app_loyalty_dashboard')]
    #[IsGranted('ROLE_MANAGER')]
    public function dashboard(): Response
    {
        return $this->redirectToRoute('app_engagement_dashboard', [], Response::HTTP_PERMANENTLY_REDIRECT);
    }

    #[Route('/my-points', name: 'app_loyalty_my_points')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myPoints(): Response
    {
        return $this->redirectToRoute('app_engagement_wallet', [], Response::HTTP_PERMANENTLY_REDIRECT);
    }

    #[Route('/customer/{id}', name: 'app_loyalty_customer')]
    #[IsGranted('ROLE_MANAGER')]
    public function customerLoyalty(int $id): Response
    {
        return $this->redirectToRoute('app_engagement_customer', ['id' => $id], Response::HTTP_PERMANENTLY_REDIRECT);
    }

    #[Route('/tiers', name: 'app_loyalty_tiers')]
    #[IsGranted('ROLE_MANAGER')]
    public function tiers(): Response
    {
        return $this->redirectToRoute('app_engagement_tiers', [], Response::HTTP_PERMANENTLY_REDIRECT);
    }

    #[Route('/promotions', name: 'app_loyalty_promotions')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function promotions(): Response
    {
        return $this->redirectToRoute('app_engagement_promotions', [], Response::HTTP_PERMANENTLY_REDIRECT);
    }

    #[Route('/reports', name: 'app_loyalty_reports')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function reports(): Response
    {
        return $this->redirectToRoute('app_engagement_reports', [], Response::HTTP_PERMANENTLY_REDIRECT);
    }

    #[Route('/api/add-points/{customerId}', name: 'app_loyalty_add_points', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function addPoints(#[MapEntity(id: 'customerId')] User $customer, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $loyalty = $this->loyaltyService->awardPoints($customer, (float) ($data['amount'] ?? 0));
        return $this->json(['success' => true, 'pointsBalance' => $loyalty->getPointsBalance(), 'tier' => $loyalty->getTier()]);
    }

    #[Route('/api/redeem-points/{customerId}', name: 'app_loyalty_redeem_points', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function redeemPoints(#[MapEntity(id: 'customerId')] User $customer, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$this->loyaltyService->redeemPoints($customer, (float) ($data['points'] ?? 0))) {
            return $this->json(['error' => 'Insufficient points'], 400);
        }
        $loyalty = $this->loyaltyService->getOrCreateLoyaltyAccount($customer);
        return $this->json(['success' => true, 'pointsBalance' => $loyalty->getPointsBalance()]);
    }

    #[Route('/api/points-balance/{customerId}', name: 'app_loyalty_points_balance', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getPointsBalance(#[MapEntity(id: 'customerId')] User $customer): JsonResponse
    {
        $viewer = $this->getUser();
        if (!$viewer instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $canViewAny = $this->isGranted('ROLE_SUPER_ADMIN')
            || $this->isGranted('ROLE_SUB_ADMIN')
            || $this->isGranted('ROLE_MANAGER');

        if (!$canViewAny && $customer->getId() !== $viewer->getId()) {
            throw $this->createAccessDeniedException('You can only view your own points balance.');
        }

        return $this->json([
            'pointsBalance' => $this->loyaltyService->getAccountPoints($customer),
            'tier' => $this->loyaltyService->getCustomerTier($customer),
        ]);
    }

    #[Route('/api/create-promotion', name: 'app_loyalty_create_promotion', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function createPromotion(): JsonResponse
    {
        return $this->json(['success' => true, 'message' => 'Use /engagement/promotions for promotion management.']);
    }
}
