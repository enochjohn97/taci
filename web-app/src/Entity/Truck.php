<?php
// src/Entity/Truck.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`trucks`')]
class Truck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $registrationNumber;

    #[ORM\Column(type: 'integer')]
    private int $capacity;

    #[ORM\Column(type: 'integer')]
    private int $currentFuel = 0;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'idle';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null; // human readable

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $lastLat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $lastLng = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $driverName = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getRegistrationNumber(): string { return $this->registrationNumber; }
    public function setRegistrationNumber(string $r): self { $this->registrationNumber = $r; return $this; }
    public function getCapacity(): int { return $this->capacity; }
    public function setCapacity(int $c): self { $this->capacity = $c; return $this; }
    public function getCurrentFuel(): int { return $this->currentFuel; }
    public function setCurrentFuel(int $f): self { $this->currentFuel = $f; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $l): self { $this->location = $l; return $this; }
    public function getLastLat(): ?float { return $this->lastLat; }
    public function setLastLat(?float $lat): self { $this->lastLat = $lat; return $this; }
    public function getLastLng(): ?float { return $this->lastLng; }
    public function setLastLng(?float $lng): self { $this->lastLng = $lng; return $this; }
    public function getDriverName(): ?string { return $this->driverName; }
    public function setDriverName(?string $d): self { $this->driverName = $d; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
