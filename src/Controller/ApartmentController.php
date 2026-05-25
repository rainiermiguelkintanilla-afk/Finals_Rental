<?php

namespace App\Controller;

use App\Entity\Apartment;
use App\Form\ApartmentType;
use App\Repository\ApartmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ActivityLogRepository;
use App\Service\ActivityLogService;
use App\Service\RealtimeEventBroadcaster;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;    

#[Route('/dashboard/apartments')]
class ApartmentController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private ActivityLogRepository $activityLogRepository,
        private RealtimeEventBroadcaster $realtime,
    ) {
    }
    #[Route('/', name: 'app_apartment_index', methods: ['GET'])]
    public function index(ApartmentRepository $apartmentRepository): Response
    {
        // allow access to staff or admin
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        return $this->render('dashboard/apartment/index.html.twig', [
            'apartments' => $apartmentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_apartment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        $apartment = new Apartment();
        $form = $this->createForm(ApartmentType::class, $apartment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($apartment);
            $entityManager->flush();
            $this->handleImageUpload($form->get('image')->getData(), $apartment);
            $entityManager->flush();

            $this->realtime->publish('apartment.created', [
                'id' => $apartment->getId(),
                'name' => $apartment->getName(),
                'status' => $apartment->getStatus(),
            ]);

            // Log activity
            $this->activityLogService->logCreate(
                $this->getUser(),
                'Apartment',
                $apartment->getId(),
                "Created apartment: {$apartment->getName()}"
            );

            $this->addFlash('success', 'Apartment created successfully!');
            return $this->redirectToRoute('app_apartment_index');
        }

        return $this->render('dashboard/apartment/new.html.twig', [
            'apartment' => $apartment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_apartment_show', methods: ['GET'])]
    public function show(Apartment $apartment): Response
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        return $this->render('dashboard/apartment/show.html.twig', [
            'apartment' => $apartment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_apartment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Apartment $apartment, EntityManagerInterface $entityManager): Response
    {
        // Staff can only edit records they created. Admins bypass this check.
        if (!$this->isGranted('ROLE_ADMIN')) {
            // non-admins must be staff and creator
            if (!$this->isGranted('ROLE_STAFF')) {
                throw $this->createAccessDeniedException('You must be staff to edit apartments.');
            }
            $currentUser = $this->getUser();
            if (!$currentUser || !method_exists($currentUser, 'getId')) {
                throw $this->createAccessDeniedException('Invalid user or user ID not accessible.');
            }
            $creatorId = $this->activityLogRepository->findCreator('Apartment', $apartment->getId());
            $currentUserId = $currentUser instanceof \App\Entity\User && method_exists($currentUser, 'getId') ? $currentUser->getId() : null;
            if ($creatorId !== $currentUserId) {
                $this->addFlash('error', 'You can only edit records you created.');
                return $this->redirectToRoute('app_apartment_index');
            }
        }

        $form = $this->createForm(ApartmentType::class, $apartment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form->get('image')->getData(), $apartment);
            $entityManager->flush();

            $this->realtime->publish('apartment.updated', [
                'id' => $apartment->getId(),
                'name' => $apartment->getName(),
                'status' => $apartment->getStatus(),
            ]);

            // Log activity
            $this->activityLogService->logUpdate(
                $this->getUser(),
                'Apartment',
                $apartment->getId(),
                "Updated apartment: {$apartment->getName()}"
            );

            $this->addFlash('success', 'Apartment updated successfully!');
            return $this->redirectToRoute('app_apartment_index');
        }

        return $this->render('dashboard/apartment/edit.html.twig', [
            'apartment' => $apartment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_apartment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Apartment $apartment, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$apartment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_apartment_show', ['id' => $apartment->getId()]);
        }

        // Prevent deletion if there are existing leases referencing this apartment
        if (method_exists($apartment, 'getLeases') && $apartment->getLeases()->count() > 0) {
            $this->addFlash('error', sprintf('Cannot delete apartment because it has %d lease(s). Remove or reassign leases first.', $apartment->getLeases()->count()));
            return $this->redirectToRoute('app_apartment_show', ['id' => $apartment->getId()]);
        }

        $apartmentId = $apartment->getId();
        $apartmentName = $apartment->getName();

        $entityManager->remove($apartment);
        $entityManager->flush();

        $this->realtime->publish('apartment.deleted', [
            'id' => $apartmentId,
            'name' => $apartmentName,
        ]);

        // Log activity
        $this->activityLogService->logDelete(
            $this->getUser(),
            'Apartment',
            $apartmentId,
            "Deleted apartment: {$apartmentName}"
        );

        $this->addFlash('success', 'Apartment deleted successfully!');
        return $this->redirectToRoute('app_apartment_index');
    }

    private function handleImageUpload(mixed $file, Apartment $apartment): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }

        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/apartments';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $extension = $file->guessExtension() ?: 'jpg';
        $idPart = $apartment->getId() !== null ? (string) $apartment->getId() : 'new';
        $safeName = sprintf('apt_%s_%s.%s', $idPart, bin2hex(random_bytes(6)), $extension);
        $file->move($uploadDir, $safeName);
        $apartment->setImage('uploads/apartments/'.$safeName);
    }
}
