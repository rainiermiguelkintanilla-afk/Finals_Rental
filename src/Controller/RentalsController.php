<?php

namespace App\Controller;

use App\Entity\ClientRentals;
use App\Form\ClientRentalsType;
use App\Repository\ClientRentalsRepository;
use App\Service\RealtimeEventBroadcaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rentals')]
final class RentalsController extends AbstractController
{
    public function __construct(
        private readonly RealtimeEventBroadcaster $realtime,
    ) {
    }

    #[Route('/', name: 'app_rentals_index', methods: ['GET'])]
    public function index(ClientRentalsRepository $clientRentalsRepository): Response
    {
        return $this->render('rentals/index.html.twig', [
            'client_rentals' => $clientRentalsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_rentals_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $clientRental = new ClientRentals();
        $form = $this->createForm(ClientRentalsType::class, $clientRental);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($clientRental);
            $entityManager->flush();

            $this->realtime->publish('booking.created', [
                'id' => $clientRental->getId(),
                'userId' => $clientRental->getUser()?->getId(),
                'status' => $clientRental->getStatus(),
            ]);

            $this->addFlash('success', 'Rental booking created successfully!');
            return $this->redirectToRoute('app_dashboard_index');
        }

        return $this->render('rentals/new.html.twig', [
            'client_rental' => $clientRental,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rentals_show', methods: ['GET'])]
    public function show(ClientRentals $clientRental): Response
    {
        return $this->render('rentals/show.html.twig', [
            'client_rental' => $clientRental,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_rentals_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ClientRentals $clientRental, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClientRentalsType::class, $clientRental);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->realtime->publish('booking.updated', [
                'id' => $clientRental->getId(),
                'userId' => $clientRental->getUser()?->getId(),
                'status' => $clientRental->getStatus(),
            ]);

            $this->addFlash('success', 'Rental booking updated successfully!');
            return $this->redirectToRoute('app_rentals_index');
        }

        return $this->render('rentals/edit.html.twig', [
            'client_rental' => $clientRental,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rentals_delete', methods: ['POST'])]
    public function delete(Request $request, ClientRentals $clientRental, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$clientRental->getId(), $request->request->get('_token'))) {
            $bookingId = $clientRental->getId();
            $userId = $clientRental->getUser()?->getId();
            $entityManager->remove($clientRental);
            $entityManager->flush();
            $this->realtime->publish('booking.deleted', [
                'id' => $bookingId,
                'userId' => $userId,
            ]);
            $this->addFlash('success', 'Rental booking deleted successfully!');
        }

        return $this->redirectToRoute('app_rentals_index');
    }
}
