<?php
// src/Entity/TransferLog.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`transfer_logs`')]
class TransferLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sale::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Sale $sale = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $action; // approve, decline, edit

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $performedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $previousValues = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $newValues = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getSale(): ?Sale { return $this->sale; }
    public function setSale(?Sale $s): self { $this->sale = $s; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $a): self { $this->action = $a; return $this; }
    public function getPerformedBy(): ?User { return $this->performedBy; }
    public function setPerformedBy(?User $u): self { $this->performedBy = $u; return $this; }
    public function getPreviousValues(): ?string { return $this->previousValues; }
    public function setPreviousValues(?string $p): self { $this->previousValues = $p; return $this; }
    public function getNewValues(): ?string { return $this->newValues; }
    public function setNewValues(?string $n): self { $this->newValues = $n; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
