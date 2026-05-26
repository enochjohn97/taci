<?php
// src/Controller/TrackingController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tracking')]
#[IsGranted(expression: "is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_SUB_ADMIN') or is_granted('ROLE_MANAGER')")]
class TrackingController extends AbstractController
{
    #[Route('', name: 'app_tracking')]
    public function index(): Response
    {
        // Mock data for Fuel Truck Dispatches
        $fuelTrucks = [
            [
                'plate' => 'T-8472-XP',
                'status' => 'in_transit',
                'driver' => 'John Doe',
                'capacity' => '30,000 Liters (Diesel)',
                'dispatchTime' => (new \DateTime('-2 hours'))->format('Y-m-d H:i'),
                'arrivalEstimate' => (new \DateTime('+1 hour'))->format('Y-m-d H:i'),
                'progress' => 65
            ],
            [
                'plate' => 'T-9123-YZ',
                'status' => 'loading',
                'driver' => 'Mike Smith',
                'capacity' => '20,000 Liters (Petrol)',
                'dispatchTime' => null,
                'arrivalEstimate' => (new \DateTime('+4 hours'))->format('Y-m-d H:i'),
                'progress' => 10
            ],
            [
                'plate' => 'T-5511-AB',
                'status' => 'arrived',
                'driver' => 'Sam Wilson',
                'capacity' => '35,000 Liters (Kerosene)',
                'dispatchTime' => (new \DateTime('-6 hours'))->format('Y-m-d H:i'),
                'arrivalEstimate' => (new \DateTime('-10 minutes'))->format('Y-m-d H:i'),
                'progress' => 100
            ]
        ];

        // Mock data for Bulk Stock Item Shipments
        $stockShipments = [
            [
                'itemName' => 'Engine Oil (5L) x 200',
                'cargoStatus' => 'dispatched',
                'truckPlate' => 'C-2234-RT',
                'progress' => 45,
                'date' => (new \DateTime('-1 day'))->format('M d, Y')
            ],
            [
                'itemName' => 'Car Batteries x 50',
                'cargoStatus' => 'delayed',
                'truckPlate' => 'C-8812-KL',
                'progress' => 30,
                'date' => (new \DateTime('-2 days'))->format('M d, Y')
            ],
            [
                'itemName' => 'Lubricants (Assorted) x 500',
                'cargoStatus' => 'delivered',
                'truckPlate' => 'C-1100-MM',
                'progress' => 100,
                'date' => (new \DateTime('-3 days'))->format('M d, Y')
            ]
        ];

        return $this->render('tracking/index.html.twig', [
            'fuel_trucks' => $fuelTrucks,
            'stock_shipments' => $stockShipments,
        ]);
    }
}
