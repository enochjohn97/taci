<?php
// src/Entity/LoginAttempt.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`login_attempts`')]
#[ORM\Index(columns: ['username'], name: 'idx_login_attempt_username')]
#[ORM\Index(columns: ['ip_address'], name: 'idx_ip_address')]
#[ORM\Index(columns: ['attempted_at'], name: 'idx_attempted_at')]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $username;

    #[ORM\Column(name: 'ip_address', type: 'string', length: 50)]
    private string $ipAddress;

    #[ORM\Column(type: 'boolean')]
    private bool $successful;

    #[ORM\Column(name: 'attempted_at', type: 'datetime')]
    private \DateTime $attemptedAt;

    public function __construct()
    {
        $this->attemptedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): self
    {
        $this->successful = $successful;
        return $this;
    }

    public function getAttemptedAt(): \DateTime
    {
        return $this->attemptedAt;
    }
}
