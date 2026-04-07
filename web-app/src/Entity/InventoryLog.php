<?php
// src/Entity/InventoryLog.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`inventory_logs`')]
#[ORM\Index(columns: ['product_id'], name: 'idx_product_id')]
#[ORM\Index(columns: ['performed_by'], name: 'idx_performed_by')]
class InventoryLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'string', length: 50)]
    private string $actionType; // in, out, adjustment, return

    #[ORM\Column(type: 'integer')]
    private int $quantityChanged;

    #[ORM\Column(type: 'integer')]
    private int $stockBefore;

    #[ORM\Column(type: 'integer')]
    private int $stockAfter;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $performedBy;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $timestamp;

    public function __construct()
    {
        $this->timestamp = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): self
    {
        $this->actionType = $actionType;
        return $this;
    }

    public function getQuantityChanged(): int
    {
        return $this->quantityChanged;
    }

    public function setQuantityChanged(int $quantityChanged): self
    {
        $this->quantityChanged = $quantityChanged;
        return $this;
    }

    public function getStockBefore(): int
    {
        return $this->stockBefore;
    }

    public function setStockBefore(int $stockBefore): self
    {
        $this->stockBefore = $stockBefore;
        return $this;
    }

    public function getStockAfter(): int
    {
        return $this->stockAfter;
    }

    public function setStockAfter(int $stockAfter): self
    {
        $this->stockAfter = $stockAfter;
        return $this;
    }

    public function getPerformedBy(): User
    {
        return $this->performedBy;
    }

    public function setPerformedBy(User $performedBy): self
    {
        $this->performedBy = $performedBy;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTime $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }
}
