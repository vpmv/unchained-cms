<?php

namespace App\Twig;

use App\System\Application\Database\Junction;
use App\System\Application\Database\JunctionList;
use App\System\Configuration\Route;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class UnchainedExtension extends AbstractExtension
{
    /**
     * @var \Twig\Environment
     */
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function route(mixed $route): string|Route
    {
        if (!$route instanceof Route) {
            return $route;
        }
        return $this->twig->getFunction('url')->getCallable()($route->getName(), $route->getParams());
    }

    public function routeAuthenticated(mixed $message)
    {
        return !$message instanceof Route || $message->isAuthenticated();
    }

    public function truncate($message, ?int $length = 100)
    {
        if (strlen($message) > $length) {
            $message = substr($message, 0, $length - 3) . '...';
        }
        return $message;
    }

    public function tableDataFilter(array $cellData): ?string
    {
        if (!$cellData['value']) {
            return null;
        }

        if ($cellData['raw'] instanceof JunctionList) {
            $result = array_map(function (Junction $junction) {
                return $junction->getExposed();
            }, $cellData['raw']->getJunctions());
        } elseif ($cellData['raw'] instanceof Junction) {
            $result = (array)$cellData['raw']->getExposed();
        }

        return implode('; ', $result);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('truncate', $this->truncate(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('route', $this->route(...)),
            new TwigFunction('routeAuthenticated', $this->routeAuthenticated(...)),
            new TwigFunction('dataFilter', $this->tableDataFilter(...)),
        ];
    }
}
