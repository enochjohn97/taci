<?php
// src/Controller/LandingController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class LandingController extends AbstractController
{
    #[Route('/landing', name: 'app_landing')]
    public function landing(): Response
    {
        return $this->render('landing/index.html.twig');
    }
}
