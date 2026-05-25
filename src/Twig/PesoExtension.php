<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class PesoExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('peso', $this->formatPeso(...)),
        ];
    }

    public function formatPeso(float|int|string|null $amount, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return '₱' . number_format(0, $decimals);
        }

        return '₱' . number_format((float) $amount, $decimals);
    }
}
