<?php
// src/Entity/Sale.php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SaleRepository;

#[ORM\Entity(repositoryClass: SaleRepository::class)]
#[ORM\Table(name: '`sales`')]
#[ORM\Index(columns: ['cashier_id'], name: 'idx_cashier')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class Sale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $cashier;

    #[ORM\Column(type: 'float')]
    private float $totalAmount;

    #[ORM\Column(type: 'float')]
    private float $discountAmount = 0;

    #[ORM\Column(type: 'float')]
    private float $loyaltyPointsUsed = 0;

    #[ORM\Column(type: 'string', length: 50)]
    private string $paymentMethod; // cash, card, transfer, ussd

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'completed'])]
    private string $status = 'completed'; // completed, voided, refunded

    #[ORM\OneToMany(mappedBy: 'sale', targetEntity: SaleItem::class, cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $receiptPath = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashier(): User
    {
        return $this->cashier;
    }

    public function setCashier(User $cashier): self
    {
        $this->cashier = $cashier;
        return $this;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): self
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(float $discountAmount): self
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getLoyaltyPointsUsed(): float
    {
        return $this->loyaltyPointsUsed;
    }

    public function setLoyaltyPointsUsed(float $loyaltyPointsUsed): self
    {
        $this->loyaltyPointsUsed = $loyaltyPointsUsed;
        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SaleItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSale($this);
        }
        return $this;
    }

    public function removeItem(SaleItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getSale() === $this) {
                $item->setSale(null);
            }
        }
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

    public function getReceiptPath(): ?string
    {
        return $this->receiptPath;
    }

    public function setReceiptPath(?string $receiptPath): self
    {
        $this->receiptPath = $receiptPath;
        return $this;
    }

    public function getNetAmount(): float
    {
        return $this->totalAmount - $this->discountAmount;
    }

    public function getItemCount(): int
    {
        return $this->items->count();
    }
}
