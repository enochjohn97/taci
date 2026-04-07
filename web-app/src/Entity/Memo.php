<?php
// src/Entity/Memo.php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`memos`')]
#[ORM\Index(columns: ['sender_id'], name: 'idx_sender')]
class Memo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $sender;

    #[ORM\Column(type: 'string', length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'draft'])]
    private string $status = 'draft'; // draft, sent, read, approved, declined

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $approvalNotes = null;

    #[ORM\ManyToOne(targetEntity: Memo::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Memo $parentMemo = null;

    #[ORM\OneToMany(mappedBy: 'memo', targetEntity: MemoRecipient::class, cascade: ['persist', 'remove'])]
    private Collection $recipients;

    #[ORM\OneToMany(mappedBy: 'memo', targetEntity: MemoAttachment::class, cascade: ['persist', 'remove'])]
    private Collection $attachments;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->recipients = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): User
    {
        return $this->sender;
    }

    public function setSender(User $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
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

    public function getApprovalNotes(): ?string
    {
        return $this->approvalNotes;
    }

    public function setApprovalNotes(?string $approvalNotes): self
    {
        $this->approvalNotes = $approvalNotes;
        return $this;
    }

    public function getParentMemo(): ?Memo
    {
        return $this->parentMemo;
    }

    public function setParentMemo(?Memo $parentMemo): self
    {
        $this->parentMemo = $parentMemo;
        return $this;
    }

    public function getRecipients(): Collection
    {
        return $this->recipients;
    }

    public function addRecipient(MemoRecipient $recipient): self
    {
        if (!$this->recipients->contains($recipient)) {
            $this->recipients->add($recipient);
            $recipient->setMemo($this);
        }
        return $this;
    }

    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(MemoAttachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setMemo($this);
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
}
