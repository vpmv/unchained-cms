<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TruncateExtension extends AbstractExtension
{

    public function truncate($message, ?int $length = 100)
    {
        if (strlen($message) > $length) {
            $message = substr($message, 0, $length - 3) . '...';
        }
        return $message;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('truncate', $this->truncate(...)),
        ];
    }
}
