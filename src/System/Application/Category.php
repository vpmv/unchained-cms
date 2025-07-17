<?php

namespace App\System\Application;

use App\System\Application\Database\Repository;
use App\System\Configuration\ConfigStore;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Category extends Application
{
    private \App\System\Configuration\ApplicationCategory $config;

    public function __construct(string $appId, RequestStack $requestStack, ConfigStore $configStore, Repository $repository, FormBuilderInterface $formBuilder, TranslatorInterface $translator)
    {
        parent::__construct($appId, $requestStack, $configStore, $repository, $formBuilder, $translator);
        $this->config = $configStore->getCategoryConfig($appId);
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