<?php

namespace App\System\Application;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Link
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        protected string $route {
            get {
                return $this->route;
            }
        },
        private readonly array $routeParameters,
        public readonly string $title,
        public readonly ?string $icon = null,
        public readonly ?string $class = null,
    ) {
        $this->route = $urlGenerator->generate($this->route, $this->routeParameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}