<?php

namespace App\System\Application;

use App\System\Configuration\ApplicationCategory;
use App\System\Configuration\ApplicationConfig;
use App\System\Router;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Category extends Application
{
    public function __construct(
        public string $appId,
        protected ApplicationConfig|ApplicationCategory $config,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        private Router $router,
    ) {
        $this->requestStack = $requestStack;
        $this->translator   = $translator;
    }

    public function boot(?string $module = null): void
    {
        return; // nothing to validate
    }

    public function apply(array $params = []): void
    {
        return;
    }

    public function run(): ?array
    {
        if (!$this->accepted) {
            return null;
        }

        $data = [
            'categoryId'  => $this->appId,
            'domain'      => Property::domain($this->appId, 'category_'),
            'route'       => $this->router->matchApp($this->appId),
            'description' => $this->config->getDescription(),
            'label'       => $this->config->getLabel(),
        ];

        return $data;
    }
}