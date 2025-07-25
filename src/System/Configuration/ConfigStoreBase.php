<?php

namespace App\System\Configuration;

use App\System\Constructs\Cacheable;
use Symfony\Component\Routing\Exception\NoConfigurationException;

class ConfigStoreBase extends Cacheable
{
    use YamlReader;

    private const BASE_KEYS = [
        'category',
        'order',
        'public',
        'routes',
    ];

    /** @var \App\System\Configuration\ApplicationConfig[] */
    protected $applications = [];
    /** @var ApplicationCategory[] */
    protected $applicationCategories = [];

    protected $systemConfig = [];

    protected $paths         = [];
    protected $authenticated = false;

    public function __construct(string $projectDir)
    {
        parent::__construct('config.', 'cache/unchained');
        $this->basePath = $projectDir . '/user/config/';
    }

    public function readApplicationConfig(string $appId): array
    {
        return $this->remember('application.raw.' . $appId, function () use ($appId) {
            $baseConfig = $this->readSystemConfig('applications', 'applications')[$appId] ?? null;
            if (null === $baseConfig) {
                throw new \LogicException('Error 50: <applications.appID> must exist; You\'ve done the impossible!');
            }

            $config = $this->readYamlFile('applications/' . $appId . '.yaml');
            if (empty($config['application'])) {
                throw new NoConfigurationException('No configuration found for App<' . $appId . '>');
            }

            foreach (self::BASE_KEYS as $key) {
                if ($baseConfig[$key] ?? null) {
                    $config['application'][$key] = $baseConfig[$key];
                }
            }

            return $config['application'];
        });
    }

    public function getCategoryConfig(string $categoryId): ApplicationCategory
    {
        if (!isset($this->applicationCategories[$categoryId])) {
            $categories = $this->readSystemConfig('applications', 'categories', []) + ['_default' => []];
            if (!array_key_exists($categoryId, $categories)) {
                $categoryId = '_default';
            }

            $this->applicationCategories[$categoryId] = $this->remember('category.' . $categoryId, function () use ($categories, $categoryId) {
                return new ApplicationCategory($categoryId, $categories[$categoryId] ?? []);
            });
        }

        return $this->applicationCategories[$categoryId];
    }

    /**
     * @param string      $name
     * @param null|string $attribute
     * @param null|mixed  $default
     *
     * @return mixed
     */
    public function readSystemConfig(string $name, ?string $attribute = null, mixed $default = null): mixed
    {
        $this->systemConfig[$name] = $this->remember('system.' . $name, function () use ($name) {
            return $this->readYamlFile($name . '.yaml');
        });

        if ($attribute) {
            return $this->systemConfig[$name][$attribute] ?? $default;
        }

        return $this->systemConfig[$name] ?? $default;
    }

    public function getUnchainedConfig(): UnchainedConfig
    {
        $config = $this->readSystemConfig('config', 'config');
        return $this->remember('unchained.config', function () use ($config) {
            return new UnchainedConfig(
                $config['title'] ?? 'Unchained',
                ($config['theme'] ?? $config['default_theme']) ?? 'auto',
                $config['dashboard'] ?? [],
                $config['navigation'] ?? [],
            );
        });
    }
}