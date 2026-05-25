<?php

namespace App\Service;

use App\Entity\Apartment;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Same apartment photos as the staff dashboard — asset mapper paths + uploads.
 */
final class ApartmentImageResolver
{
    private const FALLBACK_FILES = [
        'apt3.jpg',
        'apt4.jpg',
        'apt5.jpg',
        'apt6.jpg',
        'apt7.jpg',
        'apt8.jpg',
    ];

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function resolvePublicPath(Apartment $apartment): string
    {
        $stored = trim((string) ($apartment->getImage() ?? ''));
        if ($stored !== '') {
            if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
                return $stored;
            }

            if (str_starts_with($stored, '/uploads/')) {
                return $stored;
            }

            if (str_starts_with($stored, 'uploads/')) {
                return '/'.$stored;
            }

            if (str_starts_with($stored, '/assets/')) {
                return $stored;
            }

            $logical = str_starts_with($stored, 'img/') ? $stored : 'img/'.$stored;
            $mapped = $this->assetMapper->getPublicPath($logical);
            if ($mapped !== null) {
                return $mapped;
            }

            $uploadPath = '/uploads/apartments/'.$stored;
            $fullPath = $this->kernel->getProjectDir().'/public'.$uploadPath;
            if (is_file($fullPath)) {
                return $uploadPath;
            }
        }

        $id = (int) ($apartment->getId() ?? 0);
        $fallback = self::FALLBACK_FILES[max(0, $id - 1) % count(self::FALLBACK_FILES)];
        $mapped = $this->assetMapper->getPublicPath('img/'.$fallback);

        return $mapped ?? '/assets/img/'.$fallback;
    }

    public function resolveAbsoluteUrl(Apartment $apartment, ?Request $request = null): string
    {
        $path = $this->resolvePublicPath($apartment);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = $request?->getSchemeAndHttpHost() ?? '';

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    public function serializeForApi(Apartment $apartment, ?Request $request = null): array
    {
        $path = $this->resolvePublicPath($apartment);
        $filename = basename(parse_url($path, PHP_URL_PATH) ?: $path);

        return [
            'image' => $apartment->getImage() ?: $filename,
            'imageUrl' => $this->resolveAbsoluteUrl($apartment, $request),
        ];
    }
}
