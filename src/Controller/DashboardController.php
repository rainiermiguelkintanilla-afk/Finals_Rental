<?php

namespace App\Controller;

use App\Entity\Apartment;
use App\Entity\Tenant;
use App\Entity\Payment;
use App\Entity\Project;
use App\Entity\SalesReport;
use App\Entity\Lease;
use App\Form\ProfileFormType;
use App\Form\ChangePasswordFormType;
use App\Repository\ApartmentRepository;
use App\Repository\TenantRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProjectRepository;
use App\Repository\SalesReportRepository;
use App\Repository\LeaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }
    #[Route('/', name: 'app_dashboard_index', methods: ['GET'])]
    public function index(
        ApartmentRepository $apartmentRepository,
        TenantRepository $tenantRepository,
        PaymentRepository $paymentRepository,
        ProjectRepository $projectRepository,
        SalesReportRepository $salesReportRepository,
        LeaseRepository $leaseRepository
    ): Response {
        $apartments = $apartmentRepository->findAll();
        $tenants = $tenantRepository->findAll();
        $payments = $paymentRepository->findAll();
        $projects = $projectRepository->findAll();
        $salesReports = $salesReportRepository->findAll();
        $leases = $leaseRepository->findAll();

        // Calculate statistics
        $totalApartments = count($apartments);
        $totalTenants = count($tenants);
        $totalPayments = count($payments);
        $totalProjects = count($projects);
        $totalLeases = count($leases);
        $occupiedApartments = count(array_filter($apartments, fn($apt) => $apt->isOccupied()));
        $availableApartments = count(array_filter($apartments, fn($apt) => !$apt->isOccupied()));
        
        $totalRevenue = 0;
        foreach ($payments as $payment) {
            if ($payment->getStatus() === 'paid') {
                $totalRevenue += (float) $payment->getAmount();
            }
        }

        // Calculate total sales from sales reports
        $totalSales = 0;
        foreach ($salesReports as $report) {
            if ($report->getStatus() === 'paid') {
                $totalSales += (float) $report->getPrice();
            }
        }

        // Get featured project (first project or create sample data)
        $featuredProject = $projectRepository->findOneBy([]);
        if (!$featuredProject) {
            $featuredProject = new Project();
            $featuredProject->setName('Foreigner Tower 220');
            $featuredProject->setDescription('Apartment 12 No. st. north point, USA');
            $featuredProject->setAddress('Apartment 12 No. st. north point, USA');
            $featuredProject->setPrice('1500');
            $featuredProject->setTotalProperties(20);
            $featuredProject->setTotalSqft(1000);
            $featuredProject->setTeamSize(20);
            $featuredProject->setStatus('active');
        }

        // Generate monthly sales data for charts
        $monthlySales = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($months as $month) {
            $monthlySales[$month] = rand(1000, 4000);
        }

        // Get recent sales reports (limit to 6)
        $recentSales = array_slice($salesReports, -6);

        return $this->render('dashboard/index.html.twig', [
            'apartments' => $apartments,
            'tenants' => $tenants,
            'payments' => $payments,
            'projects' => $projects,
            'salesReports' => $salesReports,
            'leases' => $leases,
            'featuredProject' => $featuredProject,
            'recentSales' => $recentSales,
            'monthlySales' => $monthlySales,
            'totalApartments' => $totalApartments,
            'totalTenants' => $totalTenants,
            'totalPayments' => $totalPayments,
            'totalProjects' => $totalProjects,
            'totalLeases' => $totalLeases,
            'occupiedApartments' => $occupiedApartments,
            'availableApartments' => $availableApartments,
            'totalRevenue' => $totalRevenue,
            'totalSales' => $totalSales,
        ]);
    }

    #[Route('/notifications', name: 'app_dashboard_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        return $this->render('dashboard/notifications.html.twig');
    }

    #[Route('/profile', name: 'app_dashboard_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_dashboard_profile');
        }

        return $this->render('dashboard/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/settings', name: 'app_dashboard_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            $this->addFlash('error', 'Please log in to access settings.');
            return $this->redirectToRoute('app_login');
        }

        $passwordForm = $this->createForm(ChangePasswordFormType::class);
        $passwordForm->handleRequest($request);

        // Handle password change form submission
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $currentPassword = $passwordForm->get('currentPassword')->getData();
            $newPassword = $passwordForm->get('newPassword')->getData();

            // Verify current password
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
            } else {
                // Hash and set new password
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $entityManager->flush();

                $this->addFlash('success', 'Password changed successfully!');
                return $this->redirectToRoute('app_dashboard_settings');
            }
        }

        // Handle settings form submission (notification preferences, etc.)
        // Only process if password form wasn't submitted
        if ($request->isMethod('POST') && $request->request->has('save_settings') && !$passwordForm->isSubmitted()) {
            // Validate CSRF token
            $token = $request->request->get('_csrf_token') ?? $request->request->get('_token');
            if (!$this->isCsrfTokenValid('submit', $token)) {
                $this->addFlash('error', 'Invalid security token. Please try again.');
                return $this->redirectToRoute('app_dashboard_settings');
            }
            
            $user->setNotifyEmail($request->request->getBoolean('emailNotifications'));
            $user->setNotifyPaymentReminders($request->request->getBoolean('paymentReminders'));
            $user->setNotifyMaintenance($request->request->getBoolean('maintenanceAlerts'));
            $user->setNotifyPush($request->request->getBoolean('pushNotifications', true));
            $entityManager->flush();

            $this->addFlash('success', 'Notification settings saved successfully!');
            return $this->redirectToRoute('app_dashboard_settings');
        }

        return $this->render('dashboard/settings.html.twig', [
            'user' => $user,
            'passwordForm' => $passwordForm,
        ]);
    }

    #[Route('/messages', name: 'app_dashboard_messages', methods: ['GET'])]
    public function messages(): Response
    {
        return $this->render('dashboard/messages.html.twig');
    }

    #[Route('/sell', name: 'app_dashboard_sell', methods: ['GET'])]
    public function sell(): Response
    {
        return $this->render('dashboard/sell.html.twig');
    }

    #[Route('/search', name: 'app_dashboard_search', methods: ['GET'])]
    public function search(
        Request $request,
        ApartmentRepository $apartmentRepository,
        TenantRepository $tenantRepository,
        PaymentRepository $paymentRepository,
        LeaseRepository $leaseRepository
    ): Response {
        $query = trim($request->query->get('q', ''));
        $results = [
            'apartments' => [],
            'tenants' => [],
            'payments' => [],
            'leases' => [],
        ];

        if (!empty($query)) {
            $searchTerm = '%' . $query . '%';
            
            try {
                // Search apartments
                $results['apartments'] = $apartmentRepository->createQueryBuilder('a')
                    ->where('a.name LIKE :query OR a.address LIKE :query OR a.description LIKE :query')
                    ->setParameter('query', $searchTerm)
                    ->getQuery()
                    ->getResult();

                // Search tenants
                $results['tenants'] = $tenantRepository->createQueryBuilder('t')
                    ->where('t.firstName LIKE :query OR t.lastName LIKE :query OR t.email LIKE :query OR t.phone LIKE :query')
                    ->setParameter('query', $searchTerm)
                    ->getQuery()
                    ->getResult();

                // Search payments
                if (is_numeric($query)) {
                    $results['payments'] = $paymentRepository->createQueryBuilder('p')
                        ->where('p.amount = :amount OR p.status LIKE :query')
                        ->setParameter('amount', (float) $query)
                        ->setParameter('query', $searchTerm)
                        ->getQuery()
                        ->getResult();
                } else {
                    $results['payments'] = $paymentRepository->createQueryBuilder('p')
                        ->where('p.status LIKE :query')
                        ->setParameter('query', $searchTerm)
                        ->getQuery()
                        ->getResult();
                }

                // Search leases
                if (is_numeric($query)) {
                    $results['leases'] = $leaseRepository->createQueryBuilder('l')
                        ->where('l.id = :id')
                        ->setParameter('id', (int) $query)
                        ->getQuery()
                        ->getResult();
                } else {
                    $results['leases'] = [];
                }
            } catch (\Exception $e) {
                // If there's an error, return empty results
                $results = [
                    'apartments' => [],
                    'tenants' => [],
                    'payments' => [],
                    'leases' => [],
                ];
            }
        }

        return $this->render('dashboard/search.html.twig', [
            'query' => $query,
            'results' => $results,
            'total_results' => count($results['apartments']) + count($results['tenants']) + count($results['payments']) + count($results['leases']),
        ]);
    }
}
