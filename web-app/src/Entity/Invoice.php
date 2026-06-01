<?php
// src/Entity/Invoice.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`invoices`')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(type: 'float')]
    private float $amount;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getSupplier(): Supplier { return $this->supplier; }
    public function setSupplier(Supplier $s): self { $this->supplier = $s; return $this; }

    public function getAmount(): float { return $this->amount; }
    public function setAmount(float $a): self { $this->amount = $a; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $r): self { $this->reference = $r; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
