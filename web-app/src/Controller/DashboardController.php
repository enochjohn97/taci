<?php
// src/Controller/DashboardController.php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Sale;
use App\Entity\Product;
use App\Entity\FuelEntry;
use App\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'app_dashboard')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function dashboard(): Response
    {
        $user  = $this->getUser();
        $roles = $user ? $user->getRoles() : [];

        // Redirect based on role
        if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return $this->redirectToRoute('app_dashboard_super_admin');
        }
        if (in_array('ROLE_SUB_ADMIN', $roles, true)) {
            return $this->redirectToRoute('app_dashboard_sub_admin');
        }
        if (in_array('ROLE_MANAGER', $roles, true)) {
            return $this->redirectToRoute('app_dashboard_manager');
        }

        return $this->redirectToRoute('app_role_select');
    }

    private function getDashboardData($user, string $dashboardType, string $roleTheme): array
    {
        // ── Live DB data ──
        $today = new \DateTime();
        $today->setTime(0, 0);

        $sales = $this->em->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->where('s.createdAt >= :today')
            ->andWhere('s.status = :status')
            ->setParameter('today', $today)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getResult();

        $totalSalesAmount  = array_sum(array_map(fn($s) => $s->getTotalAmount(), $sales));
        $totalTransactions = count($sales);

        $lowStockProducts = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->where('p.stockQuantity <= p.reorderLevel')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $unreadNotifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        // ── Derived live metrics (replace mock arrays with DB-driven aggregates) ──
        $labels = array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23));

        // Fuel sales by hour per product category (e.g., Premium, Diesel, Regular)
        $fuelSalesByHour = ['labels' => $labels];
        $categoryHourSums = [];
        foreach ($sales as $s) {
            $hour = (int)$s->getCreatedAt()->format('G');
            foreach ($s->getItems() as $item) {
                $cat = $item->getProduct() ? $item->getProduct()->getCategory() : 'unknown';
                $catKey = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $cat));
                if (!isset($categoryHourSums[$catKey])) {
                    $categoryHourSums[$catKey] = array_fill(0, 24, 0);
                }
                $categoryHourSums[$catKey][$hour] += $item->getQuantity();
            }
        }
        // Attach category arrays using friendly keys (fallback to empty series)
        foreach ($categoryHourSums as $catKey => $arr) {
            $fuelSalesByHour[$catKey] = $arr;
        }

        // Tank levels: attempt to gather from FuelEntry or inventory logs when available
        $tankLevels = [];
        try {
            $tankRepo = $this->em->getRepository(\App\Entity\FuelEntry::class);
            $recentEntries = $tankRepo->createQueryBuilder('f')
                ->orderBy('f.createdAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
            // If domain has tank concepts, map them; otherwise leave empty and let UI handle defaults
            foreach ($recentEntries as $idx => $entry) {
                if ($idx > 2) break;
                $tankLevels[] = ['id' => 'T-'.$idx, 'name' => 'Tank '.$idx, 'capacity' => 30000, 'current' => (int)$entry->getLiterQuantity(), 'type' => 'Unknown'];
            }
        } catch (\Throwable $e) {
            $tankLevels = [];
        }

        // Pump statuses: placeholder empty array (pump status requires pump hardware integration)
        $pumpStatuses = [];

        // Delivery zones, shift notes, customer feedback, task list and other UX lists should be populated
        // by their respective repositories; return empty arrays when no data is present.
        $deliveryZones = [];
        $shiftStart = (new \DateTime())->setTime(6, 0, 0)->getTimestamp();
        $shiftNotes = [];
        $customerFeedback = [];
        $taskList = [];
        $upcomingDelivery = [];
        $safetyChecklist = [];
        $approvalRequests = [];

        // Sparkline revenue and liters for last 7 days (derived from sales)
        $sparklineRevenue = [];
        $sparklineLiters = [];
        $today = new \DateTime();
        for ($d = 6; $d >= 0; $d--) {
            $day = (clone $today)->modify("-{$d} days")->setTime(0,0);
            $dayEnd = (clone $day)->modify('+1 day');
            $daySales = $this->em->getRepository(\App\Entity\Sale::class)
                ->createQueryBuilder('s')
                ->where('s.createdAt >= :start')
                ->andWhere('s.createdAt < :end')
                ->setParameter('start', $day)
                ->setParameter('end', $dayEnd)
                ->getQuery()
                ->getResult();
            $dayTotal = array_sum(array_map(fn($s) => $s->getTotalAmount(), $daySales));
            $liters = 0;
            foreach ($daySales as $ds) {
                foreach ($ds->getItems() as $it) {
                    $liters += $it->getQuantity();
                }
            }
            $sparklineRevenue[] = (int)$dayTotal;
            $sparklineLiters[] = (int)$liters;
        }

        return [
            'view_mode'              => 'dashboard_overview',
            'dashboard_type'         => $dashboardType,
            'role_theme'             => $roleTheme,
            'total_sales_amount'     => $totalSalesAmount,
            'total_transactions'     => $totalTransactions,
            'low_stock_products'     => $lowStockProducts,
            'unread_notifications_count' => count($unreadNotifications),
            'fuel_sales_by_hour'     => $fuelSalesByHour,
            'tank_levels'            => $tankLevels,
            'delivery_zones'         => $deliveryZones,
            'approval_requests'      => $approvalRequests,
            'sparkline_revenue'      => $sparklineRevenue,
            'sparkline_liters'       => $sparklineLiters,
            'pump_statuses'          => $pumpStatuses,
            'shift_start'            => $shiftStart,
            'shift_notes'            => $shiftNotes,
            'customer_feedback'      => $customerFeedback,
            'task_list'              => $taskList,
            'upcoming_delivery'      => $upcomingDelivery,
            'safety_checklist'       => $safetyChecklist,
        ];
    }

    #[Route('/super-admin', name: 'app_dashboard_super_admin')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function superAdminDashboard(Request $request): Response
    {

        $data = $this->getDashboardData($this->getUser(), 'overview', 'super_admin');
        return $this->render('dashboard/index.html.twig', $data);
    }

    #[Route('/sub-admin', name: 'app_dashboard_sub_admin')]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function subAdminDashboard(Request $request): Response
    {

        $data = $this->getDashboardData($this->getUser(), 'overview', 'sub_admin');
        return $this->render('dashboard/index.html.twig', $data);
    }

    #[Route('/manager', name: 'app_dashboard_manager')]
    #[IsGranted('ROLE_MANAGER')]
    public function managerDashboard(Request $request): Response
    {

        $data = $this->getDashboardData($this->getUser(), 'operational', 'manager');
        return $this->render('dashboard/index.html.twig', $data);
    }

    #[Route('/analytics', name: 'app_analytics')]
    #[IsGranted('ROLE_MANAGER')]
    public function analytics(): Response
    {
        $thirtyDaysAgo = (new \DateTime())->modify('-30 days');
        
        $sales = $this->em->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->where('s.createdAt >= :date')
            ->setParameter('date', $thirtyDaysAgo)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $salesByDay = [];
        foreach ($sales as $sale) {
            $day = $sale->getCreatedAt()->format('Y-m-d');
            if (!isset($salesByDay[$day])) {
                $salesByDay[$day] = 0;
            }
            $salesByDay[$day] += $sale->getTotalAmount();
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'reports_dashboard',
            'sales_by_day' => json_encode($salesByDay),
        ]);
    }
}
