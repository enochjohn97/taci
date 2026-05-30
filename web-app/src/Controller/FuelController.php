<?php
// src/Controller/FuelController.php

namespace App\Controller;

use App\Entity\FuelEntry;
use App\Entity\FuelQuotaReport;
use App\Service\FuelAnalyticsService;
use App\Service\FuelQuotaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Fuel ops: Super Admin full access; Sub Admin operational access (see sidebar). Delegates use ROLE_MANAGER and cannot access. */
#[Route('/fuel')]
#[IsGranted('ROLE_SUB_ADMIN')]
class FuelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FuelQuotaService $fuelQuotaService,
        private FuelAnalyticsService $fuelAnalyticsService,
    ) {}

    #[Route('', name: 'app_fuel_dashboard')]
    public function dashboard(): Response
    {
        $entries = $this->em->getRepository(FuelEntry::class)
            ->findBy([], ['createdAt' => 'DESC'], 15);

        $reports = $this->em->getRepository(FuelQuotaReport::class)
            ->findBy([], ['createdAt' => 'DESC'], 5);

        $summary = $this->fuelAnalyticsService->getSummary();

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'fuel_dashboard',
            'entries' => $entries,
            'reports' => $reports,
            'total_fuel_received' => $summary['total_liters'],
            'total_entries_count' => $summary['total_entries'],
            'avg_unit_price' => $summary['avg_unit_price'],
            'fuel_chart_data' => $this->fuelAnalyticsService->getChartSeriesJson(),
        ]);
    }

    #[Route('/entry/new', name: 'app_fuel_entry_new', methods: ['GET', 'POST'])]
    public function newEntry(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['liters']) || !isset($data['unitPrice'])) {
                return $this->json(['error' => 'Invalid data: liters and unitPrice are required'], 400);
            }
            
            $entry = new FuelEntry();
            $entry->setLiterQuantity((float) $data['liters']);
            $entry->setUnitPrice((float) $data['unitPrice']);
            $entry->setEnteredBy($this->getUser());

            $this->em->persist($entry);
            $this->em->flush();

            return $this->json(['success' => true, 'entryId' => $entry->getId()]);
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'fuel_entry',
        ]);
    }

    #[Route('/quota/compute', name: 'app_fuel_quota_compute', methods: ['POST'])]
    public function computeQuota(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['entryId'])) {
            return $this->json(['error' => 'Invalid data: entryId is required'], 400);
        }
        $entryId = $data['entryId'];
        $daysInPeriod = (int) ($data['daysInPeriod'] ?? 30);
        $goodDayMultiplier = (float) ($data['goodDayMultiplier'] ?? 1.2);
        $badDayMultiplier = (float) ($data['badDayMultiplier'] ?? 0.8);
        $costPerLiter = (float) ($data['costPerLiter'] ?? 500);
        $sellingPrice = (float) ($data['sellingPrice'] ?? 650);

        $fuelEntry = $this->em->getRepository(FuelEntry::class)->find($entryId);
        if (!$fuelEntry) {
            return $this->json(['error' => 'Entry not found'], 404);
        }

        $report = $this->fuelQuotaService->generateQuotaReport(
            $fuelEntry,
            $daysInPeriod,
            $goodDayMultiplier,
            $badDayMultiplier,
            $costPerLiter,
            $sellingPrice
        );

        return $this->json([
            'success' => true,
            'reportId' => $report->getId(),
            'pdfPath' => $report->getPdfPath(),
            'excelPath' => $report->getExcelPath(),
            'metrics' => [
                'totalLiters' => $fuelEntry->getLiterQuantity(),
                'dailyQuota' => $report->getDailyQuota(),
                'daysInPeriod' => $report->getDaysInPeriod(),
                'projectedRevenue' => $report->getProjectedRevenue(),
                'projectedCogs' => $report->getProjectedCogs(),
                'projectedProfit' => $report->getProjectedProfit(),
                'profitMargin' => $report->getProfitMarginPercentage(),
                'unitPrice' => $fuelEntry->getUnitPrice(),
            ],
            'urls' => [
                'view' => $this->generateUrl('app_fuel_quota_view', ['id' => $report->getId()]),
                'pdf' => $this->generateUrl('app_fuel_quota_download_pdf', ['id' => $report->getId()]),
                'excel' => $report->getExcelPath()
                    ? $this->generateUrl('app_fuel_quota_download_excel', ['id' => $report->getId()])
                    : null,
            ],
        ]);
    }

    #[Route('/quota/{id}', name: 'app_fuel_quota_view')]
    public function viewQuota(FuelQuotaReport $report): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'fuel_quota_view',
            'report' => $report,
        ]);
    }

    #[Route('/quota/{id}/download-pdf', name: 'app_fuel_quota_download_pdf')]
    public function downloadQuotaPdf(FuelQuotaReport $report): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $report->getPdfPath();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('PDF not found');
        }

        return $this->file($filePath, 'quota-' . $report->getId() . '.pdf');
    }

    #[Route('/quota/{id}/download-excel', name: 'app_fuel_quota_download_excel')]
    public function downloadQuotaExcel(FuelQuotaReport $report): Response
    {
        if (!$report->getExcelPath()) {
            throw $this->createNotFoundException('Excel file not found');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $report->getExcelPath();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Excel file not found');
        }

        return $this->file($filePath, 'quota-' . $report->getId() . '.xlsx');
    }

    #[Route('/price-history', name: 'app_fuel_price_history')]
    public function priceHistory(): Response
    {
        $entries = $this->em->getRepository(FuelEntry::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'fuel_price_history',
            'entries' => $entries,
        ]);
    }

    #[Route('/api/current-price', name: 'app_fuel_current_price', methods: ['GET'])]
    public function getCurrentPrice(): Response
    {
        $latestEntry = $this->em->getRepository(FuelEntry::class)
            ->findOneBy([], ['createdAt' => 'DESC']);

        if (!$latestEntry) {
            return $this->json(['error' => 'No price set'], 404);
        }

        return $this->json([
            'unitPrice' => $latestEntry->getUnitPrice(),
            'lastUpdated' => $latestEntry->getCreatedAt(),
            'updatedBy' => $latestEntry->getEnteredBy()->getUsername(),
        ]);
    }
}
