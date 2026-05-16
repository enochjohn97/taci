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
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[IsGranted('ROLE_SUB_ADMIN')]
    #[IsGranted('ROLE_MANAGER')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        
        // Get today's sales
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

        $totalSalesAmount = array_sum(array_map(fn($s) => $s->getTotalAmount(), $sales));
        $totalTransactions = count($sales);

        // Low stock products
        $lowStockProducts = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->where('p.stockQuantity <= p.reorderLevel')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Unread notifications
        $unreadNotifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'dashboard',
            'total_sales_amount' => $totalSalesAmount,
            'total_transactions' => $totalTransactions,
            'low_stock_products' => $lowStockProducts,
            'unread_notifications_count' => count($unreadNotifications),
        ]);
    }

    #[Route('/super-admin', name: 'app_dashboard_super_admin')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function superAdminDashboard(): Response
    {
        $user = $this->getUser();
        
        // Get today's sales across all stores
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

        $totalSalesAmount = array_sum(array_map(fn($s) => $s->getTotalAmount(), $sales));
        $totalTransactions = count($sales);

        // Low stock products
        $lowStockProducts = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->where('p.stockQuantity <= p.reorderLevel')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Unread notifications
        $unreadNotifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'dashboard_super_admin',
            'total_sales_amount' => $totalSalesAmount,
            'total_transactions' => $totalTransactions,
            'low_stock_products' => $lowStockProducts,
            'unread_notifications_count' => count($unreadNotifications),
        ]);
    }

    #[Route('/sub-admin', name: 'app_dashboard_sub_admin')]
    #[IsGranted('ROLE_SUB_ADMIN')]
    public function subAdminDashboard(): Response
    {
        $user = $this->getUser();
        
        // Get today's sales
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

        $totalSalesAmount = array_sum(array_map(fn($s) => $s->getTotalAmount(), $sales));
        $totalTransactions = count($sales);

        // Low stock products
        $lowStockProducts = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->where('p.stockQuantity <= p.reorderLevel')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Unread notifications
        $unreadNotifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'dashboard_sub_admin',
            'total_sales_amount' => $totalSalesAmount,
            'total_transactions' => $totalTransactions,
            'low_stock_products' => $lowStockProducts,
            'unread_notifications_count' => count($unreadNotifications),
        ]);
    }

    #[Route('/manager', name: 'app_dashboard_manager')]
    #[IsGranted('ROLE_MANAGER')]
    public function managerDashboard(): Response
    {
        $user = $this->getUser();
        
        // Get today's sales
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

        $totalSalesAmount = array_sum(array_map(fn($s) => $s->getTotalAmount(), $sales));
        $totalTransactions = count($sales);

        // Low stock products
        $lowStockProducts = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->where('p.stockQuantity <= p.reorderLevel')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Unread notifications
        $unreadNotifications = $this->em->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'dashboard_manager',
            'total_sales_amount' => $totalSalesAmount,
            'total_transactions' => $totalTransactions,
            'low_stock_products' => $lowStockProducts,
            'unread_notifications_count' => count($unreadNotifications),
        ]);
    }

    #[Route('/staff', name: 'app_dashboard_staff')]
    #[IsGranted('ROLE_STAFF')]
    public function staffDashboard(): Response
    {
        // Staff dashboard - redirect to store dashboard for POS operations
        return $this->redirectToRoute('app_store_dashboard');
    }

    #[Route('/analytics', name: 'app_analytics')]
    public function analytics(): Response
    {
        // Get 30 days of sales data
        $thirtyDaysAgo = (new \DateTime())->modify('-30 days');
        
        $sales = $this->em->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->where('s.createdAt >= :date')
            ->setParameter('date', $thirtyDaysAgo)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Group by day
        $salesByDay = [];
        foreach ($sales as $sale) {
            $day = $sale->getCreatedAt()->format('Y-m-d');
            if (!isset($salesByDay[$day])) {
                $salesByDay[$day] = 0;
            }
            $salesByDay[$day] += $sale->getTotalAmount();
        }

        return $this->render('dashboard/index.html.twig', [
            'view_mode' => 'analytics',
            'sales_by_day' => json_encode($salesByDay),
        ]);
    }
}
