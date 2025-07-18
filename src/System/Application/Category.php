<?php

namespace App\System\Application;

use App\System\Configuration\ConfigStore;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Category extends Application
{
    private \App\System\Configuration\ApplicationCategory $config;

    public function __construct(string $categoryId, RequestStack $requestStack, ConfigStore $configStore, TranslatorInterface $translator)
    {
        $this->appId        = $categoryId;
        $this->requestStack = $requestStack;
        $this->configStore  = $configStore;
        $this->translator   = $translator;
        $this->config       = $configStore->getCategoryConfig($categoryId);
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
            'categoryId'         => $this->appId,
            'translation_domain' => 'category_' . preg_replace('/\W+/', '_', $this->appId),
            'public_uri'         => $this->configStore->getCategoryUri($this->appId, $this->requestStack->getMainRequest()->getLocale()),
            'description'        => $this->config->getDescription(),
            'label'              => $this->config->getLabel(),
        ];

        return $data;
    }
}