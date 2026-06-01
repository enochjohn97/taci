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
        // Fetch recent deliveries from DB and present them to the tracking template.
        // If richer Truck entities are added later, replace mapping with a join.
        $deliveries = $this->em->getRepository(\App\Entity\Delivery::class)->findBy([], ['scheduledAt' => 'DESC'], 20);

        $fuelTrucks = array_map(function($d) {
            /* @var \App\Entity\Delivery $d */
            return [
                'plate' => '—',
                'driver' => $d->getAssignedDriver() ?? '—',
                'capacity' => '—',
                'status' => $d->getStatus(),
                'origin' => '—',
                'destination' => '—',
                'dispatched' => $d->getCreatedAt(),
                'eta' => $d->getScheduledAt(),
                'progress' => match($d->getStatus()) {
                    'delivered' => 100,
                    'in_transit' => 60,
                    'dispatched' => 50,
                    'pending' => 0,
                    default => 0,
                },
            ];
        }, $deliveries);

        // Map deliveries to stockShipments view (basic info)
        $stockShipments = array_map(function($d){
            return [
                'item' => 'Fuel / Goods',
                'quantity' => '—',
                'supplier' => $d->getSupplier()->getName(),
                'truck' => '—',
                'status' => $d->getStatus(),
                'expected' => $d->getScheduledAt(),
                'progress' => match($d->getStatus()) {
                    'delivered' => 100,
                    'in_transit' => 60,
                    'dispatched' => 50,
                    'pending' => 0,
                    default => 0,
                }
            ];
        }, $deliveries);

        return $this->render('tracking/index.html.twig', [
            'fuel_trucks' => $fuelTrucks,
            'stock_shipments' => $stockShipments,
            'google_maps_api_key' => $_ENV['GOOGLE_MAPS_API_KEY'] ?? null,
        ]);
    }
}
