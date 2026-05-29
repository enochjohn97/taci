<?php

namespace App\Service;

use App\Entity\FuelEntry;
use Doctrine\ORM\EntityManagerInterface;

class FuelAnalyticsService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function getSummary(): array
    {
        $repo = $this->em->getRepository(FuelEntry::class);

        $totalLiters = (float) ($repo->createQueryBuilder('f')
            ->select('SUM(f.literQuantity)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $totalEntries = (int) $repo->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $avgPrice = (float) ($repo->createQueryBuilder('f')
            ->select('AVG(f.unitPrice)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        return [
            'total_liters' => $totalLiters,
            'total_entries' => $totalEntries,
            'avg_unit_price' => $avgPrice,
        ];
    }

    /**
     * @return array{labels: string[], liters: float[], value: float[], price: float[]}
     */
    public function getChartSeries(string $granularity = 'daily', int $limit = 30): array
    {
        $entries = $this->em->getRepository(FuelEntry::class)
            ->findBy([], ['createdAt' => 'ASC']);

        $buckets = [];

        foreach ($entries as $entry) {
            $key = match ($granularity) {
                'weekly' => $entry->getCreatedAt()->format('o-\WW'),
                'monthly' => $entry->getCreatedAt()->format('Y-m'),
                default => $entry->getCreatedAt()->format('Y-m-d'),
            };

            if (!isset($buckets[$key])) {
                $buckets[$key] = ['liters' => 0.0, 'value' => 0.0, 'price_sum' => 0.0, 'count' => 0];
            }

            $liters = $entry->getLiterQuantity();
            $price = $entry->getUnitPrice();
            $buckets[$key]['liters'] += $liters;
            $buckets[$key]['value'] += $liters * $price;
            $buckets[$key]['price_sum'] += $price;
            $buckets[$key]['count']++;
        }

        $keys = array_keys($buckets);
        if (count($keys) > $limit) {
            $keys = array_slice($keys, -$limit);
        }

        $labels = [];
        $liters = [];
        $value = [];
        $price = [];

        foreach ($keys as $key) {
            $bucket = $buckets[$key];
            $labels[] = $key;
            $liters[] = round($bucket['liters'], 2);
            $value[] = round($bucket['value'], 2);
            $price[] = round($bucket['count'] > 0 ? $bucket['price_sum'] / $bucket['count'] : 0, 2);
        }

        return compact('labels', 'liters', 'value', 'price');
    }

    public function getChartSeriesJson(): string
    {
        return json_encode([
            'daily' => $this->getChartSeries('daily'),
            'weekly' => $this->getChartSeries('weekly', 12),
            'monthly' => $this->getChartSeries('monthly', 12),
        ], JSON_THROW_ON_ERROR);
    }
}
