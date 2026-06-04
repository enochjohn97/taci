<?php
// src/Controller/SalesController.php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Sale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sales')]
#[IsGranted('ROLE_MANAGER')]
class SalesController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    private function getSalesData(string $categoryFilter = null): array
    {
        $today = new \DateTime('today');
        
        $sales = $this->em->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->where('s.createdAt >= :today')
            ->andWhere('s.status = :status')
            ->setParameter('today', $today)
            ->setParameter('status', 'completed')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $totalRevenue = 0;
        $transactionCount = 0;
        $filteredSales = [];
        $totalVolume = 0; // For fuel
        
        foreach ($sales as $sale) {
            $includeSale = false;
            $saleTotal = 0;
            $saleVolume = 0;
            
            foreach ($sale->getItems() as $item) {
                $product = $item->getProduct();
                if ($product) {
                    $cat = strtolower($product->getCategory());
                    $isFuel = in_array($cat, ['pms', 'ago', 'dpk', 'fuel']);
                    
                    if ($categoryFilter === 'fuel' && $isFuel) {
                        $includeSale = true;
                        $saleTotal += $item->getSubtotal();
                        $saleVolume += $item->getQuantity();
                    } elseif ($categoryFilter === 'store' && !$isFuel) {
                        $includeSale = true;
                        $saleTotal += $item->getSubtotal();
                    } elseif (!$categoryFilter) {
                        $includeSale = true;
                        $saleTotal += $item->getSubtotal();
                        if ($isFuel) $saleVolume += $item->getQuantity();
                    }
                }
            }
            
            if ($includeSale) {
                $totalRevenue += $saleTotal;
                $totalVolume += $saleVolume;
                $transactionCount++;
                $filteredSales[] = $sale;
            }
        }

        return [
            'total_revenue' => $totalRevenue,
            'transaction_count' => $transactionCount,
            'avg_ticket' => $transactionCount > 0 ? $totalRevenue / $transactionCount : 0,
            'total_volume' => $totalVolume,
            'recent_sales' => array_slice($filteredSales, 0, 10),
            'active_pumps' => 6,
            'total_pumps' => 6,
            'pumps_out_of_service' => 0,
        ];
    }

    #[Route('', name: 'app_sales_dashboard')]
    public function dashboard(): Response
    {
        $data = $this->getSalesData();
        $data['view_mode'] = 'sales_dashboard';
        return $this->render('sales/dashboard.html.twig', $data);
    }
    


    #[Route('/fuel', name: 'app_sales_fuel')]
    public function fuel(): Response
    {
        $data = $this->getSalesData('fuel');
        $data['view_mode'] = 'sales_fuel';
        return $this->render('sales/fuel.html.twig', $data);
    }
}
