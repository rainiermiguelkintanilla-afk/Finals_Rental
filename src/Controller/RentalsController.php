<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RentalsController extends AbstractController
{
    #[Route('/', name: 'app_rentals')]
    public function index(): Response
    {
        return $this->render('rentals/index.html.twig', [
            'controller_name' => 'RentalsController',
        ]);
    }
}
