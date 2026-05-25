<?php

namespace App\Controller;

use App\Entity\ClientRentals;
use App\Entity\Tenant;
use App\Repository\ApartmentRepository;
use App\Repository\ClientRentalsRepository;
use App\Repository\LeaseRepository;
use App\Repository\PaymentRepository;
use App\Entity\Payment;
use App\Service\ApiResponseFactory;
use App\Service\ApartmentImageResolver;
use App\Service\CustomerContext;
use App\Service\PayMongoService;
use App\Service\RealtimeEventBroadcaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/customer')]
#[IsGranted('ROLE_CUSTOMER')]
class CustomerApiController extends AbstractController
{
    public function __construct(
        private readonly CustomerContext $customerContext,
        private readonly RealtimeEventBroadcaster $realtime,
        private readonly ApartmentImageResolver $apartmentImages,
        private readonly RequestStack $requestStack,
        private readonly PayMongoService $payMongo,
    ) {
    }

    #[Route('/profile', name: 'api_customer_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        $user = $this->customerContext->getUser();
        $tenant = $user->getTenant();

        return ApiResponseFactory::success([
            'user' => $this->serializeUser($user),
            'tenant' => $tenant ? $this->serializeTenant($tenant) : null,
        ], 'Profile loaded.');
    }

    #[Route('/profile', name: 'api_customer_profile_update', methods: ['PATCH', 'PUT'])]
    public function updateProfile(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->customerContext->getUser();
        $tenant = $user->getTenant();
        if ($tenant === null) {
            return ApiResponseFactory::error('Tenant profile not found.', 'tenant_not_found', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return ApiResponseFactory::error('Invalid JSON body.', 'invalid_json');
        }

        if (isset($data['fullName']) && is_string($data['fullName'])) {
            $user->setFullName(trim($data['fullName']));
        }

        if (isset($data['phone']) && is_string($data['phone'])) {
            $tenant->setPhone(trim($data['phone']));
        }

        if (isset($data['address']) && is_string($data['address'])) {
            $tenant->setAddress(trim($data['address']));
        }

        if (isset($data['emergencyContact']) && is_string($data['emergencyContact'])) {
            $tenant->setEmergencyContact(trim($data['emergencyContact']));
        }

        $entityManager->flush();

        return ApiResponseFactory::success([
            'user' => $this->serializeUser($user),
            'tenant' => $this->serializeTenant($tenant),
        ], 'Profile updated.');
    }

    #[Route('/apartments', name: 'api_customer_apartments', methods: ['GET'])]
    public function apartments(ApartmentRepository $apartmentRepository): JsonResponse
    {
        // Same inventory as staff dashboard — customers see every listing with its live status.
        $apartments = $apartmentRepository->findBy([], ['id' => 'DESC']);

        return ApiResponseFactory::success([
            'items' => array_map([$this, 'serializeApartment'], $apartments),
            'total' => count($apartments),
        ], 'Apartments loaded.');
    }

    #[Route('/apartments/{id}', name: 'api_customer_apartment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function apartment(int $id, ApartmentRepository $apartmentRepository): JsonResponse
    {
        $apartment = $apartmentRepository->find($id);
        if ($apartment === null) {
            return ApiResponseFactory::error('Apartment not found.', 'not_found', Response::HTTP_NOT_FOUND);
        }

        return ApiResponseFactory::success($this->serializeApartment($apartment), 'Apartment loaded.');
    }

    #[Route('/leases', name: 'api_customer_leases', methods: ['GET'])]
    public function leases(LeaseRepository $leaseRepository): JsonResponse
    {
        $tenant = $this->customerContext->getTenant();
        $leases = $leaseRepository->findBy(['tenant' => $tenant], ['startDate' => 'DESC']);

        return ApiResponseFactory::success([
            'items' => array_map([$this, 'serializeLease'], $leases),
            'total' => count($leases),
        ], 'Leases loaded.');
    }

    #[Route('/payments', name: 'api_customer_payments', methods: ['GET'])]
    public function payments(PaymentRepository $paymentRepository): JsonResponse
    {
        $tenant = $this->customerContext->getTenant();
        $payments = $paymentRepository->findBy(['tenant' => $tenant], ['dueDate' => 'DESC']);

        return ApiResponseFactory::success([
            'items' => array_map([$this, 'serializePayment'], $payments),
            'total' => count($payments),
            'paymongo' => [
                'enabled' => $this->payMongo->isEnabled(),
                'currency' => 'PHP',
            ],
        ], 'Payments loaded.');
    }

    #[Route('/payments/{id}/checkout', name: 'api_customer_payment_checkout', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function paymentCheckout(int $id, PaymentRepository $paymentRepository): JsonResponse
    {
        if (!$this->payMongo->isEnabled()) {
            return ApiResponseFactory::error(
                'Online payments are not configured.',
                'paymongo_disabled',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $payment = $this->findCustomerPayment($id, $paymentRepository);
        if ($payment instanceof JsonResponse) {
            return $payment;
        }

        if (!$payment->isPayableOnline()) {
            return ApiResponseFactory::error(
                'This payment cannot be paid online.',
                'payment_not_payable',
                Response::HTTP_CONFLICT,
            );
        }

        try {
            $checkout = $this->payMongo->createCheckoutLink($payment);
        } catch (\RuntimeException $exception) {
            return ApiResponseFactory::error($exception->getMessage(), 'paymongo_error', Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponseFactory::success([
            'paymentId' => $payment->getId(),
            'checkoutUrl' => $checkout['checkoutUrl'],
            'linkId' => $checkout['linkId'],
            'successRedirectUrl' => $this->payMongo->successRedirectUrl(),
        ], 'PayMongo checkout ready.');
    }

    #[Route('/payments/{id}/sync', name: 'api_customer_payment_sync', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function paymentSync(int $id, PaymentRepository $paymentRepository): JsonResponse
    {
        $payment = $this->findCustomerPayment($id, $paymentRepository);
        if ($payment instanceof JsonResponse) {
            return $payment;
        }

        if (!$this->payMongo->isEnabled() || $payment->getPaymongoLinkId() === null) {
            return ApiResponseFactory::success(
                $this->serializePayment($payment),
                'Payment status unchanged.',
            );
        }

        try {
            $this->payMongo->syncPaymentFromLink($payment);
        } catch (\RuntimeException $exception) {
            return ApiResponseFactory::error($exception->getMessage(), 'paymongo_error', Response::HTTP_BAD_GATEWAY);
        }

        $payment = $paymentRepository->find($id) ?? $payment;

        return ApiResponseFactory::success(
            $this->serializePayment($payment),
            $payment->getStatus() === 'paid' ? 'Payment confirmed.' : 'Payment still pending.',
        );
    }

    private function findCustomerPayment(int $id, PaymentRepository $paymentRepository): Payment|JsonResponse
    {
        $tenant = $this->customerContext->getTenant();
        $payment = $paymentRepository->find($id);

        if ($payment === null || $payment->getTenant()?->getId() !== $tenant?->getId()) {
            return ApiResponseFactory::error('Payment not found.', 'not_found', Response::HTTP_NOT_FOUND);
        }

        return $payment;
    }

    #[Route('/bookings', name: 'api_customer_bookings_list', methods: ['GET'])]
    public function bookings(ClientRentalsRepository $clientRentalsRepository): JsonResponse
    {
        $user = $this->customerContext->getUser();
        $bookings = $clientRentalsRepository->findBy(['user' => $user], ['checkInDate' => 'DESC']);

        return ApiResponseFactory::success([
            'items' => array_map([$this, 'serializeBooking'], $bookings),
            'total' => count($bookings),
        ], 'Bookings loaded.');
    }

    #[Route('/bookings', name: 'api_customer_bookings_create', methods: ['POST'])]
    public function createBooking(
        Request $request,
        ApartmentRepository $apartmentRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->customerContext->getUser();
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return ApiResponseFactory::error('Invalid JSON body.', 'invalid_json');
        }

        $apartmentId = $data['apartmentId'] ?? null;
        $checkIn = $data['checkInDate'] ?? null;
        $checkOut = $data['checkOutDate'] ?? null;
        $guests = $data['guests'] ?? 1;

        $validationErrors = [];
        if (!$apartmentId) {
            $validationErrors['apartmentId'] = 'Apartment is required.';
        }
        if (!$checkIn) {
            $validationErrors['checkInDate'] = 'Check-in date is required (YYYY-MM-DD).';
        }
        if (!$checkOut) {
            $validationErrors['checkOutDate'] = 'Check-out date is required (YYYY-MM-DD).';
        }

        if ($validationErrors !== []) {
            return ApiResponseFactory::error(
                'Validation failed.',
                'validation_failed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validationErrors,
            );
        }

        $apartment = $apartmentRepository->find((int) $apartmentId);
        if ($apartment === null) {
            return ApiResponseFactory::error('Apartment not found.', 'not_found', Response::HTTP_NOT_FOUND);
        }

        if ($apartment->getStatus() !== 'available') {
            return ApiResponseFactory::error('Apartment is not available for booking.', 'apartment_unavailable', Response::HTTP_CONFLICT);
        }

        try {
            $checkInDate = new \DateTime((string) $checkIn);
            $checkOutDate = new \DateTime((string) $checkOut);
        } catch (\Exception) {
            return ApiResponseFactory::error('Invalid date format. Use YYYY-MM-DD.', 'invalid_date');
        }

        if ($checkOutDate <= $checkInDate) {
            return ApiResponseFactory::error('Check-out must be after check-in.', 'invalid_date_range');
        }

        $booking = new ClientRentals();
        $booking->setClientName($user->getDisplayName());
        $booking->setApartment($apartment->getName());
        $booking->setCheckInDate($checkInDate);
        $booking->setCheckOutDate($checkOutDate);
        $booking->setGuests(max(1, (int) $guests));
        $booking->setStatus('pending');
        $booking->setUser($user);

        $entityManager->persist($booking);
        $entityManager->flush();

        $this->realtime->publish('booking.created', [
            'id' => $booking->getId(),
            'userId' => $user->getId(),
            'apartment' => $booking->getApartment(),
            'status' => $booking->getStatus(),
        ]);

        return ApiResponseFactory::success(
            $this->serializeBooking($booking),
            'Booking request submitted.',
            Response::HTTP_CREATED,
        );
    }

    private function serializeUser(\App\Entity\User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'tenantId' => $user->getTenant()?->getId(),
        ];
    }

    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->getId(),
            'firstName' => $tenant->getFirstName(),
            'lastName' => $tenant->getLastName(),
            'email' => $tenant->getEmail(),
            'phone' => $tenant->getPhone(),
            'status' => $tenant->getStatus(),
            'address' => $tenant->getAddress(),
            'emergencyContact' => $tenant->getEmergencyContact(),
            'currentApartment' => $tenant->getCurrentApartment()?->getName(),
        ];
    }

    private function serializeApartment(\App\Entity\Apartment $apartment): array
    {
        $images = $this->apartmentImages->serializeForApi(
            $apartment,
            $this->requestStack->getCurrentRequest(),
        );

        return [
            'id' => $apartment->getId(),
            'name' => $apartment->getName(),
            'address' => $apartment->getAddress(),
            'bedrooms' => $apartment->getBedrooms(),
            'bathrooms' => $apartment->getBathrooms(),
            'rentAmount' => $apartment->getRentAmount(),
            'status' => $apartment->getStatus(),
            'description' => $apartment->getDescription(),
            'image' => $images['image'],
            'imageUrl' => $images['imageUrl'],
        ];
    }

    private function serializeLease(\App\Entity\Lease $lease): array
    {
        return [
            'id' => $lease->getId(),
            'apartment' => $lease->getApartment()?->getName(),
            'startDate' => $lease->getStartDate()?->format('Y-m-d'),
            'endDate' => $lease->getEndDate()?->format('Y-m-d'),
            'monthlyRent' => $lease->getMonthlyRent(),
            'status' => $lease->getStatus(),
        ];
    }

    private function serializePayment(Payment $payment): array
    {
        $payableOnline = $this->payMongo->isEnabled() && $payment->isPayableOnline();

        return [
            'id' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'status' => $payment->getStatus(),
            'dueDate' => $payment->getDueDate()?->format('Y-m-d'),
            'paymentDate' => $payment->getPaymentDate()?->format('Y-m-d'),
            'apartment' => $payment->getApartment()?->getName(),
            'paymentMethod' => $payment->getPaymentMethod(),
            'canPayOnline' => $payableOnline,
            'paymongoLinkId' => $payment->getPaymongoLinkId(),
        ];
    }

    private function serializeBooking(ClientRentals $booking): array
    {
        return [
            'id' => $booking->getId(),
            'apartment' => $booking->getApartment(),
            'clientName' => $booking->getClientName(),
            'checkInDate' => $booking->getCheckInDate()?->format('Y-m-d'),
            'checkOutDate' => $booking->getCheckOutDate()?->format('Y-m-d'),
            'guests' => $booking->getGuests(),
            'status' => $booking->getStatus(),
            'userId' => $booking->getUser()?->getId(),
            'userEmail' => $booking->getUser()?->getEmail(),
        ];
    }
}
