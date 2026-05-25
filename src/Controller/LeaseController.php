<?php

namespace App\Controller;

use App\Entity\Lease;
use App\Form\LeaseType;
use App\Repository\LeaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/leases')]
class LeaseController extends AbstractController
{
    #[Route('/', name: 'app_lease_index', methods: ['GET'])]
    public function index(LeaseRepository $leaseRepository): Response
    {
        return $this->render('dashboard/lease/index.html.twig', [
            'leases' => $leaseRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_lease_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $lease = new Lease();
        $form = $this->createForm(LeaseType::class, $lease);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lease);
            $entityManager->flush();

            $this->addFlash('success', 'Lease created successfully!');
            return $this->redirectToRoute('app_dashboard_index');
        }

        return $this->render('dashboard/lease/new.html.twig', [
            'lease' => $lease,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lease_show', methods: ['GET'])]
    public function show(Lease $lease): Response
    {
        return $this->render('dashboard/lease/show.html.twig', [
            'lease' => $lease,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lease_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lease $lease, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LeaseType::class, $lease);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lease->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Lease updated successfully!');
            return $this->redirectToRoute('app_lease_index');
        }

        return $this->render('dashboard/lease/edit.html.twig', [
            'lease' => $lease,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lease_delete', methods: ['POST'])]
    public function delete(Request $request, Lease $lease, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lease->getId(), $request->request->get('_token'))) {
            $entityManager->remove($lease);
            $entityManager->flush();
            $this->addFlash('success', 'Lease deleted successfully!');
        }

        return $this->redirectToRoute('app_lease_index');
    }
}



