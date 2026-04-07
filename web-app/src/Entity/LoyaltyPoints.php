<?php
// src/Entity/LoyaltyPoints.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`loyalty_points`')]
#[ORM\Index(columns: ['customer_id'], name: 'idx_customer_id')]
class LoyaltyPoints
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $pointsBalance = 0;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $totalPointsEarned = 0;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $totalPointsRedeemed = 0;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'bronze'])]
    private string $tier = 'bronze'; // bronze, silver, gold, platinum

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $totalSpend = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): User
    {
        return $this->customer;
    }

    public function setCustomer(User $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getPointsBalance(): float
    {
        return $this->pointsBalance;
    }

    public function setPointsBalance(float $pointsBalance): self
    {
        $this->pointsBalance = $pointsBalance;
        return $this;
    }

    public function getTotalPointsEarned(): float
    {
        return $this->totalPointsEarned;
    }

    public function setTotalPointsEarned(float $totalPointsEarned): self
    {
        $this->totalPointsEarned = $totalPointsEarned;
        return $this;
    }

    public function getTotalPointsRedeemed(): float
    {
        return $this->totalPointsRedeemed;
    }

    public function setTotalPointsRedeemed(float $totalPointsRedeemed): self
    {
        $this->totalPointsRedeemed = $totalPointsRedeemed;
        return $this;
    }

    public function getTier(): string
    {
        return $this->tier;
    }

    public function setTier(string $tier): self
    {
        $this->tier = $tier;
        return $this;
    }

    public function getTotalSpend(): float
    {
        return $this->totalSpend;
    }

    public function setTotalSpend(float $totalSpend): self
    {
        $this->totalSpend = $totalSpend;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function addPoints(float $points): self
    {
        $this->pointsBalance += $points;
        $this->totalPointsEarned += $points;
        return $this;
    }

    public function redeemPoints(float $points): bool
    {
        if ($this->pointsBalance >= $points) {
            $this->pointsBalance -= $points;
            $this->totalPointsRedeemed += $points;
            return true;
        }
        return false;
    }

    public function updateTier(): self
    {
        if ($this->totalSpend >= 500000) {
            $this->tier = 'platinum';
        } elseif ($this->totalSpend >= 200000) {
            $this->tier = 'gold';
        } elseif ($this->totalSpend >= 50000) {
            $this->tier = 'silver';
        } else {
            $this->tier = 'bronze';
        }
        return $this;
    }

    public function getTierDiscount(): float
    {
        return match($this->tier) {
            'platinum' => 0.15,
            'gold' => 0.10,
            'silver' => 0.05,
            default => 0,
        };
    }
}
