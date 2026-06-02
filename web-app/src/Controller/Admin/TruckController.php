<?php
// src/Controller/Admin/TruckController.php

namespace App\Controller\Admin;

use App\Entity\Truck;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/fuel')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class TruckController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/trucks', name: 'admin_fuel_trucks')]
    public function index(): Response
    {
        $trucks = $this->em->getRepository(Truck::class)->findAll();
        return $this->render('admin/fuel/trucks.html.twig', ['trucks' => $trucks]);
    }

    #[Route('/trucks/new', name: 'admin_fuel_truck_new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $truck = new Truck();
            $truck->setRegistrationNumber($data['registration'] ?? 'UNKNOWN');
            $truck->setCapacity((int)($data['capacity'] ?? 0));
            $truck->setCurrentFuel((int)($data['currentFuel'] ?? 0));
            $truck->setDriverName($data['driverName'] ?? null);
            $truck->setStatus($data['status'] ?? 'idle');
            $this->em->persist($truck);
            $this->em->flush();
            $this->addFlash('success','Truck added');
            return $this->redirectToRoute('admin_fuel_trucks');
        }
        return $this->render('admin/fuel/truck_form.html.twig');
    }

    #[Route('/trucks/{id}/track', name: 'admin_fuel_truck_track')]
    public function track(Truck $truck): Response
    {
        return $this->render('admin/fuel/track.html.twig', ['truck' => $truck]);
    }

    // API endpoint to update truck location (for simulation)
    #[Route('/trucks/{id}/location', name: 'admin_fuel_truck_location', methods: ['GET','POST'])]
    public function location(Truck $truck, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            if (!$data) return new Response('Invalid', 400);
            $truck->setLastLat((float)($data['lat'] ?? $truck->getLastLat()));
            $truck->setLastLng((float)($data['lng'] ?? $truck->getLastLng()));
            $truck->setStatus($data['status'] ?? $truck->getStatus());
            $this->em->flush();
            return $this->json(['success'=>true]);
        }

        return $this->json([
            'lastLat' => $truck->getLastLat(),
            'lastLng' => $truck->getLastLng(),
            'status' => $truck->getStatus(),
            'driver' => $truck->getDriverName(),
        ]);
    }
}
