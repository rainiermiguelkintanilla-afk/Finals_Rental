<?php

namespace App\Twig;

use App\Entity\Apartment;
use App\Service\ApartmentImageResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ApartmentExtension extends AbstractExtension
{
    public function __construct(
        private readonly ApartmentImageResolver $apartmentImages,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('apartment_image_src', $this->imageSrc(...)),
        ];
    }

    public function imageSrc(Apartment $apartment): string
    {
        return $this->apartmentImages->resolvePublicPath($apartment);
    }
}
