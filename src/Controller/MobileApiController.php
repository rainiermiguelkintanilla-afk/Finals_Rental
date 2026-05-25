<?php

namespace App\Controller;

use App\Repository\ApartmentRepository;
use App\Repository\LeaseRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProjectRepository;
use App\Repository\SalesReportRepository;
use App\Repository\TenantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mobile')]
#[IsGranted('ROLE_STAFF')]
class MobileApiController extends AbstractController
{
    #[Route('/summary', name: 'api_mobile_summary', methods: ['GET'])]
    public function summary(
        ApartmentRepository $apartmentRepository,
        TenantRepository $tenantRepository,
        PaymentRepository $paymentRepository,
        ProjectRepository $projectRepository,
        SalesReportRepository $salesReportRepository,
        LeaseRepository $leaseRepository
    ): JsonResponse {
        $apartments = $apartmentRepository->findAll();
        $tenants = $tenantRepository->findAll();
        $payments = $paymentRepository->findAll();
        $projects = $projectRepository->findAll();
        $salesReports = $salesReportRepository->findAll();
        $leases = $leaseRepository->findAll();

        $totalApartments = count($apartments);
        $totalTenants = count($tenants);
        $totalPayments = count($payments);
        $totalProjects = count($projects);
        $totalLeases = count($leases);
        $occupiedApartments = count(array_filter($apartments, fn ($apt) => $apt->isOccupied()));
        $availableApartments = count(array_filter($apartments, fn ($apt) => !$apt->isOccupied()));

        $totalRevenue = 0;
        foreach ($payments as $payment) {
            if ($payment->getStatus() === 'paid') {
                $totalRevenue += (float) $payment->getAmount();
            }
        }

        $totalSales = 0;
        foreach ($salesReports as $report) {
            if ($report->getStatus() === 'paid') {
                $totalSales += (float) $report->getPrice();
            }
        }

        return $this->json([
            'success' => true,
            'message' => 'Mobile summary loaded.',
            'data' => [
                'totals' => [
                    'apartments' => $totalApartments,
                    'tenants' => $totalTenants,
                    'payments' => $totalPayments,
                    'projects' => $totalProjects,
                    'leases' => $totalLeases,
                ],
                'apartments' => [
                    'occupied' => $occupiedApartments,
                    'available' => $availableApartments,
                ],
                'financials' => [
                    'revenue' => $totalRevenue,
                    'sales' => $totalSales,
                ],
            ],
        ]);
    }

    #[Route('/apartments', name: 'api_mobile_apartments', methods: ['GET'])]
    public function apartments(ApartmentRepository $apartmentRepository): JsonResponse
    {
        $apartments = $apartmentRepository->findBy(['status' => 'available'], ['id' => 'DESC'], 50);

        $data = array_map(static function ($apartment) {
            return [
                'id' => $apartment->getId(),
                'name' => $apartment->getName(),
                'address' => $apartment->getAddress(),
                'bedrooms' => $apartment->getBedrooms(),
                'bathrooms' => $apartment->getBathrooms(),
                'rentAmount' => $apartment->getRentAmount(),
                'status' => $apartment->getStatus(),
                'description' => $apartment->getDescription(),
                'image' => $apartment->getImage(),
                'occupied' => $apartment->isOccupied(),
            ];
        }, $apartments);

        return $this->json([
            'success' => true,
            'message' => 'Apartments loaded.',
            'data' => [
                'items' => $data,
                'total' => count($data),
            ],
        ]);
    }

    #[Route('/search', name: 'api_mobile_search', methods: ['GET'])]
    public function search(
        Request $request,
        ApartmentRepository $apartmentRepository,
        TenantRepository $tenantRepository,
        PaymentRepository $paymentRepository,
        LeaseRepository $leaseRepository
    ): JsonResponse {
        $query = trim((string) $request->query->get('q', ''));

        $results = [
            'apartments' => [],
            'tenants' => [],
            'payments' => [],
            'leases' => [],
        ];

        if ($query !== '') {
            $searchTerm = '%' . $query . '%';

            $results['apartments'] = $apartmentRepository->createQueryBuilder('a')
                ->where('a.name LIKE :query OR a.address LIKE :query OR a.description LIKE :query')
                ->setParameter('query', $searchTerm)
                ->getQuery()
                ->getResult();

            $results['tenants'] = $tenantRepository->createQueryBuilder('t')
                ->where('t.firstName LIKE :query OR t.lastName LIKE :query OR t.email LIKE :query OR t.phone LIKE :query')
                ->setParameter('query', $searchTerm)
                ->getQuery()
                ->getResult();

            if (is_numeric($query)) {
                $results['payments'] = $paymentRepository->createQueryBuilder('p')
                    ->where('p.amount = :amount OR p.status LIKE :query')
                    ->setParameter('amount', (float) $query)
                    ->setParameter('query', $searchTerm)
                    ->getQuery()
                    ->getResult();

                $results['leases'] = $leaseRepository->createQueryBuilder('l')
                    ->where('l.id = :id')
                    ->setParameter('id', (int) $query)
                    ->getQuery()
                    ->getResult();
            } else {
                $results['payments'] = $paymentRepository->createQueryBuilder('p')
                    ->where('p.status LIKE :query')
                    ->setParameter('query', $searchTerm)
                    ->getQuery()
                    ->getResult();
            }
        }

        $serializeApartments = static function ($apartment) {
            return [
                'id' => $apartment->getId(),
                'name' => $apartment->getName(),
                'address' => $apartment->getAddress(),
                'bedrooms' => $apartment->getBedrooms(),
                'bathrooms' => $apartment->getBathrooms(),
                'rentAmount' => $apartment->getRentAmount(),
                'status' => $apartment->getStatus(),
            ];
        };

        $serializeTenants = static function ($tenant) {
            return [
                'id' => $tenant->getId(),
                'firstName' => $tenant->getFirstName(),
                'lastName' => $tenant->getLastName(),
                'email' => $tenant->getEmail(),
                'phone' => $tenant->getPhone(),
            ];
        };

        $serializePayments = static function ($payment) {
            return [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'status' => $payment->getStatus(),
                'dueDate' => $payment->getDueDate()?->format('Y-m-d'),
            ];
        };

        $serializeLeases = static function ($lease) {
            return [
                'id' => $lease->getId(),
                'startDate' => $lease->getStartDate()?->format('Y-m-d'),
                'endDate' => $lease->getEndDate()?->format('Y-m-d'),
                'status' => $lease->getStatus(),
            ];
        };

        return $this->json([
            'success' => true,
            'message' => 'Search results returned.',
            'data' => [
                'query' => $query,
                'results' => [
                    'apartments' => array_map($serializeApartments, $results['apartments']),
                    'tenants' => array_map($serializeTenants, $results['tenants']),
                    'payments' => array_map($serializePayments, $results['payments']),
                    'leases' => array_map($serializeLeases, $results['leases']),
                ],
                'total' => count($results['apartments']) + count($results['tenants']) + count($results['payments']) + count($results['leases']),
            ],
        ]);
    }
}

