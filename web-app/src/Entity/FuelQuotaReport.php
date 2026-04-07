<?php
// src/Entity/FuelQuotaReport.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`fuel_quota_reports`')]
class FuelQuotaReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FuelEntry::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FuelEntry $fuelEntry;

    #[ORM\Column(type: 'string', length: 50)]
    private string $dayType; // good, bad

    #[ORM\Column(type: 'integer')]
    private int $daysInPeriod;

    #[ORM\Column(type: 'float')]
    private float $dailyQuota;

    #[ORM\Column(type: 'float')]
    private float $projectedRevenue;

    #[ORM\Column(type: 'float')]
    private float $projectedCogs;

    #[ORM\Column(type: 'float')]
    private float $projectedProfit;

    #[ORM\Column(type: 'float')]
    private float $profitMarginPercentage;

    #[ORM\Column(type: 'string', length: 255)]
    private string $pdfPath;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $excelPath = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFuelEntry(): FuelEntry
    {
        return $this->fuelEntry;
    }

    public function setFuelEntry(FuelEntry $fuelEntry): self
    {
        $this->fuelEntry = $fuelEntry;
        return $this;
    }

    public function getDayType(): string
    {
        return $this->dayType;
    }

    public function setDayType(string $dayType): self
    {
        $this->dayType = $dayType;
        return $this;
    }

    public function getDaysInPeriod(): int
    {
        return $this->daysInPeriod;
    }

    public function setDaysInPeriod(int $daysInPeriod): self
    {
        $this->daysInPeriod = $daysInPeriod;
        return $this;
    }

    public function getDailyQuota(): float
    {
        return $this->dailyQuota;
    }

    public function setDailyQuota(float $dailyQuota): self
    {
        $this->dailyQuota = $dailyQuota;
        return $this;
    }

    public function getProjectedRevenue(): float
    {
        return $this->projectedRevenue;
    }

    public function setProjectedRevenue(float $projectedRevenue): self
    {
        $this->projectedRevenue = $projectedRevenue;
        return $this;
    }

    public function getProjectedCogs(): float
    {
        return $this->projectedCogs;
    }

    public function setProjectedCogs(float $projectedCogs): self
    {
        $this->projectedCogs = $projectedCogs;
        return $this;
    }

    public function getProjectedProfit(): float
    {
        return $this->projectedProfit;
    }

    public function setProjectedProfit(float $projectedProfit): self
    {
        $this->projectedProfit = $projectedProfit;
        return $this;
    }

    public function getProfitMarginPercentage(): float
    {
        return $this->profitMarginPercentage;
    }

    public function setProfitMarginPercentage(float $profitMarginPercentage): self
    {
        $this->profitMarginPercentage = $profitMarginPercentage;
        return $this;
    }

    public function getPdfPath(): string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(string $pdfPath): self
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }

    public function getExcelPath(): ?string
    {
        return $this->excelPath;
    }

    public function setExcelPath(?string $excelPath): self
    {
        $this->excelPath = $excelPath;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
