<?php
// src/Entity/MemoRecipient.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`memo_recipients`')]
class MemoRecipient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Memo::class, inversedBy: 'recipients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Memo $memo;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recipient = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $recipientRole = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $readAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMemo(): Memo
    {
        return $this->memo;
    }

    public function setMemo(Memo $memo): self
    {
        $this->memo = $memo;
        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getRecipientRole(): ?string
    {
        return $this->recipientRole;
    }

    public function setRecipientRole(?string $recipientRole): self
    {
        $this->recipientRole = $recipientRole;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        if ($isRead && !$this->readAt) {
            $this->readAt = new \DateTime();
        }
        return $this;
    }

    public function getReadAt(): ?\DateTime
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTime $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }
}
