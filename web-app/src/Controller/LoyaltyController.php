<?php
// src/Controller/LoyaltyController.php

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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/loyalty')]
class LoyaltyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoyaltyService $loyaltyService,
    ) {}

    #[Route('', name: 'app_loyalty_dashboard')]
    #[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN')]
    public function dashboard(): Response
    {
        $stats = $this->loyaltyService->getLoyaltyStats();
        $topCustomers = $this->loyaltyService->getTopCustomers(10);

        return $this->render('loyalty/dashboard.html.twig', [
            'stats' => $stats,
            'top_customers' => $topCustomers,
        ]);
    }

    #[Route('/my-points', name: 'app_loyalty_my_points')]
    #[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN|ROLE_MANAGER|ROLE_STAFF')]
    public function myPoints(): Response
    {
        $user = $this->getUser();
        $loyalty = $this->loyaltyService->getOrCreateLoyaltyAccount($user);

        return $this->render('loyalty/my-points.html.twig', [
            'loyalty' => $loyalty,
        ]);
    }

    #[Route('/customer/{id}', name: 'app_loyalty_customer')]
    #[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN')]
    public function customerLoyalty(User $customer): Response
    {
        $loyalty = $this->loyaltyService->getOrCreateLoyaltyAccount($customer);

        return $this->render('loyalty/customer-loyalty.html.twig', [
            'customer' => $customer,
            'loyalty' => $loyalty,
        ]);
    }

    #[Route('/api/add-points/{customerId}', name: 'app_loyalty_add_points', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function addPoints(User $customer, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $amount = (float) $data['amount'];

        $loyalty = $this->loyaltyService->awardPoints($customer, $amount);

        return $this->json([
            'success' => true,
            'pointsBalance' => $loyalty->getPointsBalance(),
            'tier' => $loyalty->getTier(),
        ]);
    }

    #[Route('/api/redeem-points/{customerId}', name: 'app_loyalty_redeem_points', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN')]
    public function redeemPoints(User $customer, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $points = (float) $data['points'];

        $success = $this->loyaltyService->redeemPoints($customer, $points);

        if (!$success) {
            return $this->json(['error' => 'Insufficient points'], 400);
        }

        $loyalty = $this->loyaltyService->getOrCreateLoyaltyAccount($customer);

        return $this->json([
            'success' => true,
            'pointsBalance' => $loyalty->getPointsBalance(),
            'message' => 'Points redeemed successfully',
        ]);
    }

    #[Route('/api/points-balance/{customerId}', name: 'app_loyalty_points_balance', methods: ['GET'])]
    public function getPointsBalance(User $customer): JsonResponse
    {
        $balance = $this->loyaltyService->getAccountPoints($customer);
        $tier = $this->loyaltyService->getCustomerTier($customer);

        return $this->json([
            'pointsBalance' => $balance,
            'tier' => $tier,
        ]);
    }

    #[Route('/tiers', name: 'app_loyalty_tiers')]
    #[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN')]
    public function tiers(): Response
    {
        $tierBenefits = [
            'bronze' => [
                'name' => 'Bronze',
                'minSpend' => 0,
                'discount' => 0,
                'benefits' => ['Standard support', 'Points earning']
            ],
            'silver' => [
                'name' => 'Silver',
                'minSpend' => 50000,
                'discount' => 5,
                'benefits' => ['5% discount', 'Priority support', 'Bonus points events']
            ],
            'gold' => [
                'name' => 'Gold',
                'minSpend' => 200000,
                'discount' => 10,
                'benefits' => ['10% discount', 'VIP support', '2x points on weekends']
            ],
            'platinum' => [
                'name' => 'Platinum',
                'minSpend' => 500000,
                'discount' => 15,
                'benefits' => ['15% discount', 'Dedicated account manager', '3x points on all purchases']
            ]
        ];

        return $this->render('loyalty/tiers.html.twig', [
            'tiers' => $tierBenefits,
        ]);
    }

    #[Route('/promotions', name: 'app_loyalty_promotions')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function promotions(): Response
    {
        // TODO: Fetch active promotions from database
        $promotions = [
            [
                'id' => 1,
                'name' => 'Double Points Weekend',
                'multiplier' => 2,
                'startDate' => new \DateTime('2026-04-12'),
                'endDate' => new \DateTime('2026-04-13'),
                'active' => true,
            ],
            [
                'id' => 2,
                'name' => 'New Customer Bonus',
                'multiplier' => 1.5,
                'startDate' => new \DateTime('2026-04-01'),
                'endDate' => new \DateTime('2026-04-30'),
                'active' => true,
            ]
        ];

        return $this->render('loyalty/promotions.html.twig', [
            'promotions' => $promotions,
        ]);
    }

    #[Route('/api/create-promotion', name: 'app_loyalty_create_promotion', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function createPromotion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $promotion = $this->loyaltyService->createPromotion(
            $data['name'],
            $data['multiplier'] ?? 2.0,
            new \DateTime($data['startDate']),
            new \DateTime($data['endDate'])
        );

        // TODO: Save to database

        return $this->json([
            'success' => true,
            'promotion' => $promotion,
        ]);
    }

    #[Route('/reports', name: 'app_loyalty_reports')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function reports(): Response
    {
        $loyaltyData = $this->em->getRepository(LoyaltyPoints::class)->findAll();

        $byTier = [
            'bronze' => 0,
            'silver' => 0,
            'gold' => 0,
            'platinum' => 0,
        ];

        $totalPoints = 0;
        $totalRedeemed = 0;

        foreach ($loyaltyData as $loyalty) {
            $byTier[$loyalty->getTier()]++;
            $totalPoints += $loyalty->getTotalPointsEarned();
            $totalRedeemed += $loyalty->getTotalPointsRedeemed();
        }

        return $this->render('loyalty/reports.html.twig', [
            'by_tier' => $byTier,
            'total_points' => $totalPoints,
            'total_redeemed' => $totalRedeemed,
            'total_customers' => count($loyaltyData),
        ]);
    }
}
