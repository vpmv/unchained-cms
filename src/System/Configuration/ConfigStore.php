<?php

namespace App\System\Configuration;

use App\System\Application\Field;
use App\System\Application\Property;
use App\System\Constructs\Cacheable;
use App\System\Helpers\Timer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\NoConfigurationException;

class ConfigStore extends Cacheable
{
    use YamlReader;

    public const DIR_PUBLIC    = 'public';
    public const DIR_FILES     = 'files';
    public const DIR_IMAGES    = 'images';
    public const DIR_EXTENSION = 'extension';

    /** @var \Symfony\Component\DependencyInjection\ContainerInterface */
    private $container;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \App\System\Helpers\Timer */
    private $timer;

    /** @var \App\System\Configuration\ApplicationConfig[] */
    private $applications      = [];
    private $applicationConfig = [];
    /** @var ApplicationCategory[] */
    private $applicationCategories = [];

    private $systemConfig = [];

    private $paths         = [];
    private $authenticated = false;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, Timer $timer)
    {
        parent::__construct('config.');
        $this->container = $container;
        $this->basePath  = $container->getParameter('kernel.project_dir') . '/user/config/';
        $this->logger    = $logger;
        $this->timer     = $timer;

        $this->paths         = [
            'root'   => $container->getParameter('kernel.project_dir'),
            'public' => $container->getParameter('kernel.public_dir'),
        ];
        $this->authenticated = !empty($this->container->get('security.token_storage')->getToken()->getRoleNames());
    }

    /**
     * @param string      $name
     * @param null|string $attribute
     *
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function readSystemConfig(string $name, ?string $attribute = null, $default = null)
    {
        $this->systemConfig[$name] = $this->remember('system.' . $name, function () use ($name) {
            return $this->readYamlFile('system/' . $name . '.yaml');
        });

        if ($attribute) {
            return $this->systemConfig[$name][$attribute] ?? $default;
        }

        return $this->systemConfig[$name] ?? $default;
    }

    public function readApplicationConfig(string $appId)
    {
        return $this->remember('application.raw.' . $appId, function () use ($appId) {
            $config = $this->readYamlFile('applications/' . $appId . '.yaml');
            if (empty($config['application'])) {
                $this->logger->alert('Application configuration missing <application> attribute', ['appId' => $appId]);
                throw new NoConfigurationException('No configuration found for App<' . $appId . '>');
            }

            return $config['application'];
        });
    }

    /**
     * Retrieve and parse application configuration
     *
     * @param string $appId
     *
     * @return \App\System\Configuration\ApplicationConfig
     * @throws \InvalidArgumentException if configuration file is not found
     * @throws \Symfony\Component\Routing\Exception\NoConfigurationException If configuration is missing mandatory 'application' attribute
     */
    public function getApplicationConfig(string $appId): ApplicationConfig
    {
        if (!isset($this->applications[$appId])) {
            $this->timer->start('config.' . $appId);
            $this->applications[$appId] = $this->remember('application.' . $appId, function () use ($appId) {
                $config = $this->readApplicationConfig($appId);
                $config = new ApplicationConfig($this->container, $this, $config, $appId);

                return $config;
            });
            $this->timer->stop('config.' . $appId);
        }
        $this->setApplicationSystemConfig($appId);

        return $this->applications[$appId];
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
     * @param $applicationId
     *
     * @throws \Symfony\Component\Routing\Exception\NoConfigurationException   Application not defined
     */
    private function setApplicationSystemConfig($applicationId)
    {
        if (isset($this->applicationConfig[$applicationId])) {
            return;
        }

        $applications = $this->readSystemConfig('applications', 'applications');
        if (!array_key_exists($applicationId, $applications)) {
            throw new NoConfigurationException('Application not configured');
        }
        $userLocale = $this->container->get('request_stack')->getMasterRequest()->getLocale();

        $this->applicationConfig[$applicationId] = $this->remember('sysconfig.' . $applicationId . '.' . $userLocale . '-' . intval($this->authenticated), function () use ($applicationId, $applications, $userLocale) {
            $sysConfig = $applications[$applicationId];
            $appConfig = $this->applications[$applicationId];

            $routePrefix = null;
            if ($categoryId = $appConfig->getCategory()->getCategoryId()) {
                try {
                    $categoryConfig = $this->getCategoryConfig($categoryId);
                    $routePrefix    = $categoryConfig->getRoute($userLocale, true);
                } catch (NoConfigurationException $e) {
                    $this->logger->warning(sprintf('No configuration for category "%s"', $categoryId));
                }
            }

            $routes = $appConfig->getRoutes() + ['_active' => $appConfig->getRoutes($userLocale)];
            foreach ($routes as &$route) {
                $route = $routePrefix . $route;
            }

            return [
                'uri'        => $routes,
                'authorized' => ($sysConfig['public'] ?? true) || $this->authenticated,
            ];
        });
    }

    public function isAuthorized(string $applicationId, ?string $sourceAlias = null, ?string $module = null): bool
    {
        $config = $this->getApplicationConfig($applicationId);
        if ($sourceAlias) {
            $config        = $this->getSourceConfigByAlias($config, $sourceAlias);
            $applicationId = $config->getAppId();
        }

        if (!isset($this->applicationConfig[$applicationId])) {
            throw new \InvalidArgumentException('Unconfigured application: ' . $applicationId);
        }

        $authorized = $this->applicationConfig[$applicationId]['authorized'];
        if ($authorized && $module) {
            return !empty($config->getModule($module));
        }

        return $authorized;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function getApplicationUri(string $applicationId, ?string $sourceAlias = null, ?string $locale = null)
    {
        if ($sourceAlias) {
            $applicationId = $this->getSourceConfigByAlias($this->getApplicationConfig($applicationId), $sourceAlias)->appId;
        }

        if (!isset($this->applicationConfig[$applicationId])) {
            throw new \InvalidArgumentException('Unconfigured application: ' . $applicationId);
        }
        if (empty($this->applicationConfig[$applicationId]['uri'][$locale ?: '_active'])) {
            return $this->applicationConfig[$applicationId]['uri']['_default'];
        }

        return $this->applicationConfig[$applicationId]['uri'][$locale ?: '_active'];
    }

    public function getApplicationField(string $applicationId, string $field, ?string $sourceAlias = null): Field
    {
        $config = $this->getApplicationConfig($applicationId);
        if ($sourceAlias) {
            $config = $this->getSourceConfigByAlias($config, $sourceAlias);
        }

        return $config->getField($field);
    }

    public function getCategoryUri(string $categoryId, ?string $locale = null): ?string
    {
        if (!isset($this->applicationCategories[$categoryId])) {
            try {
                $this->getCategoryConfig($categoryId);
            } catch (NoConfigurationException $e) {
                return null;
            }
        }

        return $this->applicationCategories[$categoryId]->getRoute($locale);
    }

    public function getDirectory(string $type, string $applicationId, ?string $sourceAlias = null, bool $asFileSystem = false): string
    {
        if ($type == static::DIR_PUBLIC) {
            return $this->paths['public'];
        } elseif ($type == static::DIR_EXTENSION) {
            return $this->paths['root'] . '/user/extensions';
        }

        $config = $this->getApplicationConfig($applicationId);
        if ($sourceAlias) {
            $config = $this->getSourceConfigByAlias($config, $sourceAlias);
        }

        // fixme: asFileSystem should include DIRECTORY_SEPARATOR for non-unix deployments
        switch ($type) {
            case static::DIR_IMAGES:
                return ($asFileSystem ? $this->paths['public'] : '') . '/media/images/apps/' . $config->getAppId();
            case static::DIR_FILES:
                return ($asFileSystem ? $this->paths['public'] : '') . '/media/files/apps/' . $config->getAppId();
        }

        throw new \InvalidArgumentException('Unknown directory type: ' . $type);
    }

    public function getForeignColumn(string $applicationId, ?string $sourceAlias): string
    {
        $config = $this->getApplicationConfig($applicationId);
        if ($sourceAlias) {
            $config = $this->getSourceConfigByAlias($config, $sourceAlias);
        }

        return Property::foreignKey($config->appId);
    }

    /**
     * @param string      $applicationId
     * @param string|null $sourceAlias
     *
     * @return Field[]
     * @throws \InvalidArgumentException Unknown source
     */
    public function getExposedFields(string $applicationId, ?string $sourceAlias = null): array
    {
        $config = $this->getApplicationConfig($applicationId);
        if ($sourceAlias) {
            $config = $this->getSourceConfigByAlias($config, $sourceAlias);
        }
        $fields = array_filter((array)$config->getMeta('exposes'));
        foreach ($fields as &$field) {
            $field = $config->fields[$field];
        }

        return $fields;
    }

    /**
     * @param \App\System\Configuration\ApplicationConfig $config
     * @param string                                      $source
     *
     * @return \App\System\Configuration\ApplicationConfig
     * @throws \InvalidArgumentException Unknown source
     */
    private function getSourceConfigByAlias(ApplicationConfig $config, string $source): ApplicationConfig
    {
        if (!isset($config->sources[$source])) {
            throw new \InvalidArgumentException("Unconfigured source $source in application " . $config->appId);
        }

        return $this->getApplicationConfig($config->sources[$source]['application']);
    }
}