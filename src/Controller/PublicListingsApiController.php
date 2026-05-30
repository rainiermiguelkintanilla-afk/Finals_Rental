<?php

namespace App\Controller;

use App\Repository\ApartmentRepository;
use App\Service\ApiResponseFactory;
use App\Service\ApartmentImageResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public browse endpoints for the Expo/mobile home screen (no JWT required).
 */
#[Route('/api/public')]
class PublicListingsApiController extends AbstractController
{
    public function __construct(
        private readonly ApartmentImageResolver $apartmentImages,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/apartments', name: 'api_public_apartments', methods: ['GET'])]
    public function apartments(Request $request, ApartmentRepository $apartmentRepository): JsonResponse
    {
        $status = $request->query->get('status');
        $apartments = $apartmentRepository->findForPublicApi(is_string($status) ? $status : null);
        $items = array_map([$this, 'serializeApartment'], $apartments);
        $availableCount = 0;
        foreach ($items as $row) {
            if (($row['status'] ?? '') === 'available') {
                ++$availableCount;
            }
        }

        return ApiResponseFactory::success([
            'items' => $items,
            'total' => count($apartments),
            'availableCount' => $availableCount,
        ], 'Apartments loaded.');
    }

    #[Route('/apartments/{id}', name: 'api_public_apartment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function apartment(int $id, ApartmentRepository $apartmentRepository): JsonResponse
    {
        $apartment = $apartmentRepository->find($id);
        if ($apartment === null) {
            return ApiResponseFactory::error('Apartment not found.', 'not_found', 404);
        }

        return ApiResponseFactory::success($this->serializeApartment($apartment), 'Apartment loaded.');
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
            'occupied' => $apartment->isOccupied(),
        ];
    }
}
