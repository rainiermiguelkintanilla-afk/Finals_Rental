<?php

namespace App\Controller;

use App\Repository\ApartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ApartmentRepository $apartmentRepository): Response
    {
        // Fetch available apartments for the Units section
        $apartments = $apartmentRepository->findAvailableForPublic();
        
        return $this->render('home/index.html.twig', [
            'apartments' => $apartments,
        ]);
    }
}
