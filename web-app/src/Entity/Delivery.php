<?php
// src/Entity/Delivery.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`deliveries`')]
class Delivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $scheduledAt;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $assignedDriver = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->scheduledAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getSupplier(): Supplier { return $this->supplier; }
    public function setSupplier(Supplier $s): self { $this->supplier = $s; return $this; }

    public function getScheduledAt(): \DateTime { return $this->scheduledAt; }
    public function setScheduledAt(\DateTime $d): self { $this->scheduledAt = $d; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }

    public function getAssignedDriver(): ?string { return $this->assignedDriver; }
    public function setAssignedDriver(?string $d): self { $this->assignedDriver = $d; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
