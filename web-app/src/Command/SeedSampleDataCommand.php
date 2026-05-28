<?php
// src/Command/SeedSampleDataCommand.php

namespace App\Command;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\FuelEntry;
use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\Sale;
use App\Entity\SaleItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-sample-data',
    description: 'Seeds sample products, fuel entries, sales, and notification data for testing the dashboards.',
)]
class SeedSampleDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Starting database seeding...');

        // 1. Fetch default user to associate records with
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'superadmin']);
        if (!$user) {
            $io->error('Default user "superadmin" not found. Please run app:create-default-users first.');
            return Command::FAILURE;
        }

        // 2. Clear existing sample products to prevent duplicate barcode errors
        $existingProducts = $this->em->getRepository(Product::class)->findAll();
        foreach ($existingProducts as $p) {
            $this->em->remove($p);
        }
        $this->em->flush();

        // 3. Seed Products
        $productsData = [
            [
                'name' => 'Premium Motor Spirit (PMS)',
                'category' => 'Fuel',
                'barcode' => 'PMS-001',
                'unitPrice' => 650.0,
                'costPrice' => 580.0,
                'stockQuantity' => 15000,
                'reorderLevel' => 3000,
                'description' => 'High quality petrol for vehicles.'
            ],
            [
                'name' => 'Automotive Gas Oil (AGO)',
                'category' => 'Fuel',
                'barcode' => 'AGO-001',
                'unitPrice' => 1100.0,
                'costPrice' => 980.0,
                'stockQuantity' => 12000,
                'reorderLevel' => 2500,
                'description' => 'Premium low sulfur diesel.'
            ],
            [
                'name' => 'Engine Oil 5W-30 (4L)',
                'category' => 'Lubricants',
                'barcode' => 'LUB-001',
                'unitPrice' => 24000.0,
                'costPrice' => 19000.0,
                'stockQuantity' => 80,
                'reorderLevel' => 15,
                'description' => 'Fully synthetic engine lubricant.'
            ],
            [
                'name' => 'Brake Fluid (500ml)',
                'category' => 'Lubricants',
                'barcode' => 'LUB-002',
                'unitPrice' => 3500.0,
                'costPrice' => 2800.0,
                'stockQuantity' => 8, // Triggers low-stock warning!
                'reorderLevel' => 20,
                'description' => 'High performance brake fluid.'
            ],
            [
                'name' => 'Premium Car Wash & Wax',
                'category' => 'Services',
                'barcode' => 'SRV-001',
                'unitPrice' => 5000.0,
                'costPrice' => 1000.0,
                'stockQuantity' => 100,
                'reorderLevel' => 5,
                'description' => 'Exterior pressure wash with premium wax.'
            ],
            [
                'name' => 'Wheel Alignment & Balancing',
                'category' => 'Services',
                'barcode' => 'SRV-002',
                'unitPrice' => 8000.0,
                'costPrice' => 2000.0,
                'stockQuantity' => 50,
                'reorderLevel' => 5,
                'description' => 'Full four wheel laser alignment.'
            ],
        ];

        $products = [];
        foreach ($productsData as $data) {
            $p = new Product();
            $p->setName($data['name']);
            $p->setCategory($data['category']);
            $p->setBarcode($data['barcode']);
            $p->setUnitPrice($data['unitPrice']);
            $p->setCostPrice($data['costPrice']);
            $p->setStockQuantity($data['stockQuantity']);
            $p->setReorderLevel($data['reorderLevel']);
            $p->setDescription($data['description']);
            
            $this->em->persist($p);
            $products[] = $p;
        }
        $this->em->flush();
        $io->success('Sample products seeded.');

        // 4. Seed Fuel Entries
        for ($i = 0; $i < 5; $i++) {
            $fe = new FuelEntry();
            $fe->setLiterQuantity(5000.0 + ($i * 1000));
            $fe->setUnitPrice(650.0 + ($i * 5));
            $fe->setEnteredBy($user);
            $fe->setCreatedAt(new \DateTime('-' . ($i * 2) . ' days'));
            $this->em->persist($fe);
        }
        $this->em->flush();
        $io->success('Sample fuel entries seeded.');

        // 5. Seed Sales & Transactions
        for ($i = 0; $i < 3; $i++) {
            $sale = new Sale();
            $sale->setCashier($user);
            $sale->setPaymentMethod($i === 0 ? 'cash' : ($i === 1 ? 'pos' : 'transfer'));
            $sale->setDiscountAmount($i * 500);
            $sale->setLoyaltyPointsUsed(0);
            
            $totalAmount = 0;
            // Pick a product
            $p = $products[$i % count($products)];
            
            $item = new SaleItem();
            $item->setProduct($p);
            $item->setQuantity(2 + $i);
            $item->setUnitPrice($p->getUnitPrice());
            $item->calculateSubtotal();
            
            $sale->addItem($item);
            $totalAmount += $item->getSubtotal();
            $sale->setTotalAmount($totalAmount - $sale->getDiscountAmount());
            
            $this->em->persist($sale);
        }
        $this->em->flush();
        $io->success('Sample sales transactions seeded.');

        // 6. Seed Notification
        $notif = new Notification();
        $notif->setUser($user);
        $notif->setType('low_stock');
        $notif->setMessage('Product Brake Fluid (500ml) is running low on stock');
        $notif->setLink('/inventory/alerts');
        $this->em->persist($notif);
        $this->em->flush();
        $io->success('Sample low-stock notification seeded.');

        // 7. Seed Audit Log
        $audit = new AuditLog();
        $audit->setUser($user);
        $audit->setAction('Database Seeded');
        $audit->setModule('System');
        $audit->setDescription('Seeded database with sample data successfully.');
        $audit->setIpAddress('127.0.0.1');
        $this->em->persist($audit);
        $this->em->flush();

        $io->success('Database seeded with sample data successfully.');
        return Command::SUCCESS;
    }
}
