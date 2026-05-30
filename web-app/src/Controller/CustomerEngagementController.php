<?php

namespace App\Controller;

use App\Entity\LoyaltyPoints;
use App\Entity\User;
use App\Service\LoyaltyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer Rewards Hub — replaces the legacy loyalty module with a unified engagement experience.
 */
#[Route('/engagement')]
class CustomerEngagementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoyaltyService $loyaltyService,
    ) {}

    #[Route('', name: 'app_engagement_dashboard')]
    #[IsGranted('ROLE_MANAGER')]
    public function dashboard(): Response
    {
        $stats = $this->loyaltyService->getLoyaltyStats();
        $topCustomers = $this->loyaltyService->getTopCustomers(10);

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'engagement_dashboard',
            'total_customers' => (int) ($stats['total_customers'] ?? 0),
            'total_points_issued' => (float) ($stats['total_points_issued'] ?? 0),
            'total_spend' => (float) ($stats['total_spend'] ?? 0),
            'top_customers' => $topCustomers,
        ]);
    }

    #[Route('/wallet', name: 'app_engagement_wallet')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function wallet(): Response
    {
        $loyalty = $this->loyaltyService->getOrCreateLoyaltyAccount($this->getUser());

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'engagement_wallet',
            'my_points' => $loyalty->getPointsBalance(),
            'current_tier' => $loyalty->getTier(),
            'total_spend' => $loyalty->getTotalSpend(),
            'tier_discount' => $loyalty->getTierDiscount() * 100,
        ]);
    }

    #[Route('/customer/{id}', name: 'app_engagement_customer')]
    #[IsGranted('ROLE_MANAGER')]
    public function customer(User $customer): Response
    {
        $loyalty = $this->loyaltyService->getOrCreateLoyaltyAccount($customer);

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'engagement_customer',
            'customer' => $customer,
            'loyalty' => $loyalty,
        ]);
    }

    #[Route('/tiers', name: 'app_engagement_tiers')]
    #[IsGranted('ROLE_MANAGER')]
    public function tiers(): Response
    {
        $tiers = [
            ['name' => 'Bronze', 'min' => 0, 'max' => 49999, 'color' => 'warning', 'perks' => 'Standard support, base points earning', 'discount' => 0],
            ['name' => 'Silver', 'min' => 50000, 'max' => 199999, 'color' => 'secondary', 'perks' => '5% discount, priority support, bonus events', 'discount' => 5],
            ['name' => 'Gold', 'min' => 200000, 'max' => 499999, 'color' => 'warning', 'perks' => '10% discount, VIP support, 2x weekend points', 'discount' => 10],
            ['name' => 'Platinum', 'min' => 500000, 'max' => 999999999, 'color' => 'primary', 'perks' => '15% discount, dedicated manager, 3x points', 'discount' => 15],
        ];

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'engagement_tiers',
            'tiers' => $tiers,
        ]);
    }

    #[Route('/promotions', name: 'app_engagement_promotions')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function promotions(): Response
    {
        $promotions = [
            ['name' => 'Double Points Weekend', 'multiplier' => 2, 'start_date' => '2026-04-12', 'end_date' => '2026-04-13', 'active' => true],
            ['name' => 'New Customer Bonus', 'multiplier' => 1.5, 'start_date' => '2026-04-01', 'end_date' => '2026-04-30', 'active' => true],
            ['name' => 'Fuel & Shop Combo', 'multiplier' => 1.25, 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'active' => true],
        ];

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'engagement_promotions',
            'promotions' => $promotions,
        ]);
    }

    #[Route('/reports', name: 'app_engagement_reports')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function reports(): Response
    {
        $loyaltyData = $this->em->getRepository(LoyaltyPoints::class)->findAll();

        $byTier = ['bronze' => 0, 'silver' => 0, 'gold' => 0, 'platinum' => 0];
        $totalPoints = 0;
        $totalRedeemed = 0;

        foreach ($loyaltyData as $loyalty) {
            $tier = $loyalty->getTier();
            if (isset($byTier[$tier])) {
                $byTier[$tier]++;
            }
            $totalPoints += $loyalty->getTotalPointsEarned();
            $totalRedeemed += $loyalty->getTotalPointsRedeemed();
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'engagement_reports',
            'by_tier' => $byTier,
            'total_points' => $totalPoints,
            'total_redeemed' => $totalRedeemed,
            'total_customers' => count($loyaltyData),
        ]);
    }

    #[Route('/api/add-points/{customerId}', name: 'app_engagement_add_points', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function addPoints(#[MapEntity(id: 'customerId')] User $customer, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $amount = (float) ($data['amount'] ?? 0);
        $loyalty = $this->loyaltyService->awardPoints($customer, $amount);

        return $this->json([
            'success' => true,
            'pointsBalance' => $loyalty->getPointsBalance(),
            'tier' => $loyalty->getTier(),
        ]);
    }

    #[Route('/api/redeem-points/{customerId}', name: 'app_engagement_redeem_points', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function redeemPoints(#[MapEntity(id: 'customerId')] User $customer, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $points = (float) ($data['points'] ?? 0);

        if (!$this->loyaltyService->redeemPoints($customer, $points)) {
            return $this->json(['error' => 'Insufficient points'], 400);
        }

        $loyalty = $this->loyaltyService->getOrCreateLoyaltyAccount($customer);

        return $this->json([
            'success' => true,
            'pointsBalance' => $loyalty->getPointsBalance(),
            'message' => 'Points redeemed successfully',
        ]);
    }
}
