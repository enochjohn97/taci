<?php
// src/Entity/PasswordReset.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`password_resets`')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_id')]
#[ORM\Index(columns: ['token'], name: 'idx_token')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_expires_at')]
class PasswordReset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $token;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $expiresAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $used = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $usedAt = null;

    public function __construct(User $user, string $token, int $expiryMinutes = 60)
    {
        $this->user = $user;
        $this->token = $token;
        $this->createdAt = new \DateTime();
        $this->expiresAt = (new \DateTime())->modify("+$expiryMinutes minutes");
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): self
    {
        $this->used = $used;
        if ($used) {
            $this->usedAt = new \DateTime();
        }
        return $this;
    }

    public function getUsedAt(): ?\DateTime
    {
        return $this->usedAt;
    }

    public function isExpired(): bool
    {
        return new \DateTime() > $this->expiresAt;
    }

    public function isValid(): bool
    {
        return !$this->used && !$this->isExpired();
    }
}
