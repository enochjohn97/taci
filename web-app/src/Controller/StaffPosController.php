<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffPosController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/pos', name: 'staff_pos')]
    public function pos(): Response
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        return $this->render('pos/staff_pos.html.twig', [
            'products' => $products,
        ]);
    }
}
