<?php
// src/Controller/TrackingController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/tracking')]
#[IsGranted('ROLE_SUB_ADMIN')]
class TrackingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_tracking')]
    public function index(): Response
    {
        // Placeholder tracking data — replace with real entity queries once
        // FuelTruck / Shipment entities are created.
        $fuelTrucks = [
            [
                'plate'      => 'AGL-701-A',
                'driver'     => 'Emeka Obi',
                'capacity'   => '33,000 L',
                'status'     => 'in_transit',
                'origin'     => 'Warri Depot',
                'destination'=> 'Main Station',
                'dispatched' => new \DateTimeImmutable('-3 hours'),
                'eta'        => new \DateTimeImmutable('+2 hours'),
                'progress'   => 60,
            ],
            [
                'plate'      => 'KTU-441-B',
                'driver'     => 'Chidi Nwosu',
                'capacity'   => '45,000 L',
                'status'     => 'arrived',
                'origin'     => 'Port Harcourt Terminal',
                'destination'=> 'Main Station',
                'dispatched' => new \DateTimeImmutable('-6 hours'),
                'eta'        => new \DateTimeImmutable('-30 minutes'),
                'progress'   => 100,
            ],
            [
                'plate'      => 'LAG-992-C',
                'driver'     => 'Tunde Adeyemi',
                'capacity'   => '33,000 L',
                'status'     => 'loading',
                'origin'     => 'Kaduna Refinery',
                'destination'=> 'Branch B Station',
                'dispatched' => null,
                'eta'        => new \DateTimeImmutable('+8 hours'),
                'progress'   => 15,
            ],
        ];

        $stockShipments = [
            [
                'item'       => 'Engine Oil (20W-50)',
                'quantity'   => '240 units',
                'supplier'   => 'Total Lubricants',
                'truck'      => 'AGL-701-A',
                'status'     => 'in_transit',
                'expected'   => new \DateTimeImmutable('+2 hours'),
                'progress'   => 60,
            ],
            [
                'item'       => 'Car Wash Supplies',
                'quantity'   => '60 cartons',
                'supplier'   => 'CleanMax Ltd',
                'truck'      => 'MKD-233-F',
                'status'     => 'delivered',
                'expected'   => new \DateTimeImmutable('-1 hour'),
                'progress'   => 100,
            ],
            [
                'item'       => 'Tyre Pressure Gauges',
                'quantity'   => '30 units',
                'supplier'   => 'AutoParts NG',
                'truck'      => 'RVS-557-Z',
                'status'     => 'pending',
                'expected'   => new \DateTimeImmutable('+24 hours'),
                'progress'   => 0,
            ],
        ];

        return $this->render('tracking/index.html.twig', [
            'fuel_trucks'     => $fuelTrucks,
            'stock_shipments' => $stockShipments,
            'google_maps_api_key' => $_ENV['GOOGLE_MAPS_API_KEY'] ?? null,
        ]);
    }
}
