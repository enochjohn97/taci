<?php
// src/Service/LoyaltyService.php

namespace App\Service;

use App\Entity\User;
use App\Entity\LoyaltyPoints;
use Doctrine\ORM\EntityManagerInterface;

class LoyaltyService
{
    private float $pointsPerNaira = 1.0; // Configurable by Super Admin

    public function __construct(private EntityManagerInterface $em) {}

    public function setPointsPerNaira(float $rate): self
    {
        $this->pointsPerNaira = $rate;
        return $this;
    }

    public function getOrCreateLoyaltyAccount(User $customer): LoyaltyPoints
    {
        $loyalty = $this->em->getRepository(LoyaltyPoints::class)->findOneBy(['customer' => $customer]);
        
        if (!$loyalty) {
            $loyalty = new LoyaltyPoints();
            $loyalty->setCustomer($customer);
            $this->em->persist($loyalty);
            $this->em->flush();
        }

        return $loyalty;
    }

    public function awardPoints(User $customer, float $amount): LoyaltyPoints
    {
        $loyalty = $this->getOrCreateLoyaltyAccount($customer);
        $pointsEarned = $amount * $this->pointsPerNaira;

        $loyalty->addPoints($pointsEarned);
        $loyalty->setTotalSpend($loyalty->getTotalSpend() + $amount);
        $loyalty->updateTier();

        $this->em->persist($loyalty);
        $this->em->flush();

        return $loyalty;
    }

    public function redeemPoints(User $customer, float $points): bool
    {
        $loyalty = $this->getOrCreateLoyaltyAccount($customer);
        
        if ($loyalty->redeemPoints($points)) {
            $this->em->persist($loyalty);
            $this->em->flush();
            return true;
        }

        return false;
    }

    public function getAccountPoints(User $customer): float
    {
        $loyalty = $this->getOrCreateLoyaltyAccount($customer);
        return $loyalty->getPointsBalance();
    }

    public function getCustomerTier(User $customer): string
    {
        $loyalty = $this->getOrCreateLoyaltyAccount($customer);
        return $loyalty->getTier();
    }

    public function applyTierDiscount(User $customer, float $amount): float
    {
        $loyalty = $this->getOrCreateLoyaltyAccount($customer);
        $discount = $amount * $loyalty->getTierDiscount();
        return round($discount, 2);
    }

    public function createPromotion(
        string $name,
        float $multiplier = 2.0,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null
    ): array {
        return [
            'name' => $name,
            'multiplier' => $multiplier,
            'startDate' => $startDate ?? new \DateTime(),
            'endDate' => $endDate,
            'active' => true,
        ];
    }

    public function applyPromotion(User $customer, string $promotionName, float $promotionMultiplier, float $amount): LoyaltyPoints
    {
        $loyalty = $this->getOrCreateLoyaltyAccount($customer);
        $basePoints = $amount * $this->pointsPerNaira;
        $bonusPoints = $basePoints * ($promotionMultiplier - 1);

        $loyalty->addPoints($bonusPoints);
        $this->em->persist($loyalty);
        $this->em->flush();

        return $loyalty;
    }

    public function getTopCustomers(int $limit = 10): array
    {
        return $this->em->getRepository(LoyaltyPoints::class)
            ->createQueryBuilder('lp')
            ->orderBy('lp.totalSpend', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLoyaltyStats(): array
    {
        $stats = $this->em->getRepository(LoyaltyPoints::class)
            ->createQueryBuilder('lp')
            ->select('COUNT(lp.id) as total_customers')
            ->addSelect('SUM(lp.pointsBalance) as total_points_issued')
            ->addSelect('SUM(lp.totalSpend) as total_spend')
            ->getQuery()
            ->getOneOrNullResult();

        return $stats ?? [
            'total_customers' => 0,
            'total_points_issued' => 0,
            'total_spend' => 0,
        ];
    }
}
