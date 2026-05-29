<?php
// src/Service/FuelQuotaService.php

namespace App\Service;

use App\Entity\FuelEntry;
use App\Entity\FuelQuotaReport;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class FuelQuotaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParameterBagInterface $params,
        private Environment $twig,
    ) {}

    public function generateQuotaReport(
        FuelEntry $fuelEntry,
        int $daysInPeriod = 30,
        float $goodDayMultiplier = 1.2,
        float $badDayMultiplier = 0.8,
        float $costPerLiter = 500,
        float $sellingPricePerLiter = 650
    ): FuelQuotaReport {
        // Assume 60% good days, 40% bad days
        $goodDays = (int) ($daysInPeriod * 0.6);
        $badDays = $daysInPeriod - $goodDays;

        // Calculate daily quotas
        $baseDailyQuota = $fuelEntry->getLiterQuantity() / $daysInPeriod;
        $goodDayQuota = $baseDailyQuota * $goodDayMultiplier;
        $badDayQuota = $baseDailyQuota * $badDayMultiplier;

        // Projected revenue and COGS
        $goodDayRevenue = $goodDayQuota * $sellingPricePerLiter;
        $badDayRevenue = $badDayQuota * $sellingPricePerLiter;
        $totalRevenue = ($goodDayRevenue * $goodDays) + ($badDayRevenue * $badDays);

        $goodDayCogs = $goodDayQuota * $costPerLiter;
        $badDayCogs = $badDayQuota * $costPerLiter;
        $totalCogs = ($goodDayCogs * $goodDays) + ($badDayCogs * $badDays);

        $totalProfit = $totalRevenue - $totalCogs;
        $profitMargin = ($totalProfit / $totalRevenue) * 100;

        // Generate PDF
        $pdfPath = $this->generatePdf($fuelEntry, $goodDays, $badDays, $goodDayQuota, $badDayQuota, $totalRevenue, $totalCogs, $totalProfit, $profitMargin);

        // Generate Excel
        $excelPath = $this->generateExcel($fuelEntry, $goodDays, $badDays, $goodDayQuota, $badDayQuota, $totalRevenue, $totalCogs, $totalProfit, $profitMargin);

        // Create report
        $report = new FuelQuotaReport();
        $report->setFuelEntry($fuelEntry);
        $report->setDayType('mixed');
        $report->setDaysInPeriod($daysInPeriod);
        $report->setDailyQuota($baseDailyQuota);
        $report->setProjectedRevenue($totalRevenue);
        $report->setProjectedCogs($totalCogs);
        $report->setProjectedProfit($totalProfit);
        $report->setProfitMarginPercentage($profitMargin);
        $report->setPdfPath($pdfPath);
        $report->setExcelPath($excelPath);

        $this->em->persist($report);
        $this->em->flush();

        return $report;
    }

    private function generatePdf(
        FuelEntry $fuelEntry,
        int $goodDays,
        int $badDays,
        float $goodDayQuota,
        float $badDayQuota,
        float $totalRevenue,
        float $totalCogs,
        float $totalProfit,
        float $profitMargin
    ): string {
        $daysInPeriod = $goodDays + $badDays;
        $baseDailyQuota = $fuelEntry->getLiterQuantity() / max($daysInPeriod, 1);

        $html = $this->twig->render('fuel/quota-report-pdf.html.twig', [
            'fuelEntry' => $fuelEntry,
            'goodDays' => $goodDays,
            'badDays' => $badDays,
            'daysInPeriod' => $daysInPeriod,
            'goodDayQuota' => $goodDayQuota,
            'badDayQuota' => $badDayQuota,
            'baseDailyQuota' => $baseDailyQuota,
            'totalRevenue' => $totalRevenue,
            'totalCogs' => $totalCogs,
            'totalProfit' => $totalProfit,
            'profitMargin' => $profitMargin,
            'goodDayRevenue' => $goodDayQuota * ($totalRevenue / max($goodDays * $goodDayQuota + $badDays * $badDayQuota, 1)) * $goodDays,
            'enteredBy' => $fuelEntry->getEnteredBy()?->getUsername() ?? 'System',
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'quota_' . uniqid() . '.pdf';
        $path = $this->params->get('kernel.project_dir') . '/public/reports/' . $filename;
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $dompdf->output());
        return '/reports/' . $filename;
    }

    private function generateExcel(
        FuelEntry $fuelEntry,
        int $goodDays,
        int $badDays,
        float $goodDayQuota,
        float $badDayQuota,
        float $totalRevenue,
        float $totalCogs,
        float $totalProfit,
        float $profitMargin
    ): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fuel Quota Report');

        $sheet->setCellValue('A1', 'FUEL QUOTA REPORT');
        $sheet->setCellValue('A3', 'Total Liters');
        $sheet->setCellValue('B3', $fuelEntry->getLiterQuantity());
        
        $sheet->setCellValue('A5', 'Good Days');
        $sheet->setCellValue('B5', $goodDays);
        $sheet->setCellValue('C5', 'Daily Quota');
        $sheet->setCellValue('D5', $goodDayQuota);

        $sheet->setCellValue('A6', 'Bad Days');
        $sheet->setCellValue('B6', $badDays);
        $sheet->setCellValue('C6', 'Daily Quota');
        $sheet->setCellValue('D6', $badDayQuota);

        $sheet->setCellValue('A8', 'Projected Revenue');
        $sheet->setCellValue('B8', $totalRevenue);

        $sheet->setCellValue('A9', 'Cost of Goods');
        $sheet->setCellValue('B9', $totalCogs);

        $sheet->setCellValue('A10', 'Projected Profit');
        $sheet->setCellValue('B10', $totalProfit);

        $sheet->setCellValue('A11', 'Profit Margin %');
        $sheet->setCellValue('B11', $profitMargin);

        $writer = new Xlsx($spreadsheet);
        $filename = 'quota_' . uniqid() . '.xlsx';
        $path = $this->params->get('kernel.project_dir') . '/public/reports/' . $filename;
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer->save($path);
        return '/reports/' . $filename;
    }
}
