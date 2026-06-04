<?php
// src/Entity/FuelEntry.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`fuel_entries`')]
class FuelEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'float')]
    private float $literQuantity;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $unitPrice = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'entered_by', nullable: false, onDelete: 'CASCADE')]
    private User $enteredBy;

    #[ORM\Column(type: 'integer')]
    private int $pumpNumber;

    #[ORM\Column(type: 'string', length: 50)]
    private string $fuelType;

    #[ORM\Column(type: 'string', length: 50)]
    private string $paymentMethod;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $vehiclePlate = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $attendant;

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

    public function getLiterQuantity(): float
    {
        return $this->literQuantity;
    }

    public function setLiterQuantity(float $literQuantity): self
    {
        $this->literQuantity = $literQuantity;
        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getEnteredBy(): User
    {
        return $this->enteredBy;
    }

    public function setEnteredBy(User $enteredBy): self
    {
        $this->enteredBy = $enteredBy;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
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

    public function getPumpNumber(): int
    {
        return $this->pumpNumber;
    }

    public function setPumpNumber(int $pumpNumber): self
    {
        $this->pumpNumber = $pumpNumber;
        return $this;
    }

    public function getFuelType(): string
    {
        return $this->fuelType;
    }

    public function setFuelType(string $fuelType): self
    {
        $this->fuelType = $fuelType;
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

    public function getVehiclePlate(): ?string
    {
        return $this->vehiclePlate;
    }

    public function setVehiclePlate(?string $vehiclePlate): self
    {
        $this->vehiclePlate = $vehiclePlate;
        return $this;
    }

    public function getAttendant(): string
    {
        return $this->attendant;
    }

    public function setAttendant(string $attendant): self
    {
        $this->attendant = $attendant;
        return $this;
    }
}
