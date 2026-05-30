<?php

namespace App\Controller;

use App\Repository\ApartmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/about')]
class AboutController extends AbstractController
{
    #[Route('', name: 'app_about_index', methods: ['GET'])]
    public function index(ApartmentRepository $apartmentRepository): Response
    {
        $apartments = $apartmentRepository->findAvailableForPublic();

        return $this->render('about/index.html.twig', [
            'apartments' => $apartments,
        ]);
    }
}

