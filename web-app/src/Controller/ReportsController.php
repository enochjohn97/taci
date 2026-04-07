<?php
// src/Controller/ReportsController.php

namespace App\Controller;

use App\Entity\Sale;
use App\Entity\Product;
use App\Entity\InventoryLog;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_SUPER_ADMIN|ROLE_SUB_ADMIN')]
class ReportsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'app_reports_dashboard')]
    public function dashboard(Request $request): Response
    {
        $startDate = new \DateTime($request->query->get('startDate', '2026-04-01'));
        $endDate = new \DateTime($request->query->get('endDate', 'now'));
        $endDate->setTime(23, 59, 59);

        // Get sales data
        $sales = $this->em->getRepository(Sale::class)
            ->findByDateRange($startDate, $endDate);

        $totalRevenue = array_sum(array_map(fn($s) => $s->getTotalAmount(), $sales));
        $totalTransactions = count($sales);
        $averageTransaction = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Group by date
        $salesByDate = [];
        foreach ($sales as $sale) {
            $date = $sale->getCreatedAt()->format('Y-m-d');
            if (!isset($salesByDate[$date])) {
                $salesByDate[$date] = 0;
            }
            $salesByDate[$date] += $sale->getTotalAmount();
        }

        return $this->render('reports/dashboard.html.twig', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'average_transaction' => $averageTransaction,
            'sales_by_date' => json_encode($salesByDate),
            'sales' => $sales,
        ]);
    }

    #[Route('/sales', name: 'app_reports_sales')]
    public function sales(Request $request): Response
    {
        $period = $request->query->get('period', 'daily'); // daily, weekly, monthly, yearly
        $startDate = new \DateTime($request->query->get('startDate', '-30 days'));
        $endDate = new \DateTime($request->query->get('endDate', 'now'));
        $endDate->setTime(23, 59, 59);

        $sales = $this->em->getRepository(Sale::class)
            ->findByDateRange($startDate, $endDate);

        // Group sales by period
        $groupedSales = [];
        foreach ($sales as $sale) {
            $key = match($period) {
                'weekly' => $sale->getCreatedAt()->format('Y-W'),
                'monthly' => $sale->getCreatedAt()->format('Y-m'),
                'yearly' => $sale->getCreatedAt()->format('Y'),
                default => $sale->getCreatedAt()->format('Y-m-d'),
            };

            if (!isset($groupedSales[$key])) {
                $groupedSales[$key] = [
                    'amount' => 0,
                    'count' => 0,
                    'discount' => 0,
                ];
            }

            $groupedSales[$key]['amount'] += $sale->getTotalAmount();
            $groupedSales[$key]['count']++;
            $groupedSales[$key]['discount'] += $sale->getDiscountAmount();
        }

        return $this->render('reports/sales.html.twig', [
            'period' => $period,
            'grouped_sales' => $groupedSales,
            'total_amount' => array_sum(array_map(fn($g) => $g['amount'], $groupedSales)),
            'total_discount' => array_sum(array_map(fn($g) => $g['discount'], $groupedSales)),
        ]);
    }

    #[Route('/inventory', name: 'app_reports_inventory')]
    public function inventory(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();

        $inventory = [];
        $totalValue = 0;
        $lowStockCount = 0;

        foreach ($products as $product) {
            $value = $product->getStockQuantity() * ($product->getCostPrice() ?? $product->getUnitPrice());
            $inventory[] = [
                'product' => $product,
                'value' => $value,
                'margin' => $product->getMarginPercentage(),
            ];
            $totalValue += $value;

            if ($product->isLowStock()) {
                $lowStockCount++;
            }
        }

        return $this->render('reports/inventory.html.twig', [
            'inventory' => $inventory,
            'total_value' => $totalValue,
            'low_stock_count' => $lowStockCount,
        ]);
    }

    #[Route('/profit-loss', name: 'app_reports_profit_loss')]
    public function profitLoss(Request $request): Response
    {
        $startDate = new \DateTime($request->query->get('startDate', '-30 days'));
        $endDate = new \DateTime($request->query->get('endDate', 'now'));
        $endDate->setTime(23, 59, 59);

        $sales = $this->em->getRepository(Sale::class)
            ->findByDateRange($startDate, $endDate);

        $totalRevenue = 0;
        $totalCogs = 0;
        $totalDiscount = 0;

        foreach ($sales as $sale) {
            $totalRevenue += $sale->getTotalAmount() + $sale->getDiscountAmount();
            $totalDiscount += $sale->getDiscountAmount();

            foreach ($sale->getItems() as $item) {
                $cogs = $item->getQuantity() * ($item->getProduct()->getCostPrice() ?? $item->getUnitPrice());
                $totalCogs += $cogs;
            }
        }

        $grossProfit = $totalRevenue - $totalCogs;
        $netProfit = $grossProfit - $totalDiscount;
        $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        return $this->render('reports/profit-loss.html.twig', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_discount' => $totalDiscount,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
        ]);
    }

    #[Route('/export/sales-excel', name: 'app_reports_export_sales_excel')]
    public function exportSalesExcel(Request $request): Response
    {
        $startDate = new \DateTime($request->query->get('startDate', '-30 days'));
        $endDate = new \DateTime($request->query->get('endDate', 'now'));
        $endDate->setTime(23, 59, 59);

        $sales = $this->em->getRepository(Sale::class)
            ->findByDateRange($startDate, $endDate);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');

        // Headers
        $headers = ['Sale ID', 'Date', 'Cashier', 'Amount', 'Discount', 'Payment Method', 'Status'];
        $sheet->fromArray($headers, null, 'A1');

        // Data
        $row = 2;
        foreach ($sales as $sale) {
            $sheet->fromArray([
                $sale->getId(),
                $sale->getCreatedAt()->format('Y-m-d H:i:s'),
                $sale->getCashier()->getUsername(),
                $sale->getTotalAmount(),
                $sale->getDiscountAmount(),
                $sale->getPaymentMethod(),
                $sale->getStatus(),
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'sales-report-' . date('Y-m-d') . '.xlsx';

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    #[Route('/export/inventory-csv', name: 'app_reports_export_inventory_csv')]
    public function exportInventoryCsv(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();

        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, ['Product ID', 'Name', 'Category', 'Barcode', 'Unit Price', 'Stock', 'Reorder Level', 'Margin %']);

        foreach ($products as $product) {
            fputcsv($handle, [
                $product->getId(),
                $product->getName(),
                $product->getCategory(),
                $product->getBarcode(),
                $product->getUnitPrice(),
                $product->getStockQuantity(),
                $product->getReorderLevel(),
                number_format($product->getMarginPercentage(), 2),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment;filename=inventory-' . date('Y-m-d') . '.csv');

        return $response;
    }
}
