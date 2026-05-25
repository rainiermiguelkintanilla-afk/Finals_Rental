<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ActivityLogService;

#[Route('/dashboard/payments')]
class PaymentController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private ActivityLogRepository $activityLogRepository
    ) {
    }
    #[Route('/', name: 'app_payment_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): Response
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        return $this->render('dashboard/payment/index.html.twig', [
            'payments' => $paymentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($payment);
            $entityManager->flush();

            $this->activityLogService->logCreate(
                $this->getUser(),
                'Payment',
                $payment->getId(),
                "Created payment: {$payment->getAmount()}"
            );

            $this->addFlash('success', 'Payment created successfully!');
            return $this->redirectToRoute('app_payment_index');
        }

        return $this->render('dashboard/payment/new.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_payment_show', methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }
        return $this->render('dashboard/payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_payment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        // Staff can only edit records they created. Admins bypass this check.
        if (!$this->isGranted('ROLE_ADMIN')) {
            if (!$this->isGranted('ROLE_STAFF')) {
                throw $this->createAccessDeniedException('You must be staff to edit payments.');
            }
            $currentUser = $this->getUser();
            if (!$currentUser instanceof \App\Entity\User) {
                throw $this->createAccessDeniedException('Invalid user.');
            }
            if (!$currentUser) {
                throw $this->createAccessDeniedException('You must be logged in.');
            }
            $creatorId = $this->activityLogRepository->findCreator('Payment', $payment->getId());
            if ($creatorId !== $currentUser->getId()) {
                $this->addFlash('error', 'You can only edit records you created.');
                return $this->redirectToRoute('app_payment_index');
            }
        }

        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->activityLogService->logUpdate(
                $this->getUser(),
                'Payment',
                $payment->getId(),
                "Updated payment: {$payment->getAmount()}"
            );

            $this->addFlash('success', 'Payment updated successfully!');
            return $this->redirectToRoute('app_payment_index');
        }

        return $this->render('dashboard/payment/edit.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_payment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$payment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_payment_index');
        }

        $paymentId = $payment->getId();
        $paymentAmount = $payment->getAmount();

        $entityManager->remove($payment);
        $entityManager->flush();

        $this->activityLogService->logDelete(
            $this->getUser(),
            'Payment',
            $paymentId,
            "Deleted payment: {$paymentAmount}"
        );

        $this->addFlash('success', 'Payment deleted successfully!');
        return $this->redirectToRoute('app_payment_index');
    }
}
