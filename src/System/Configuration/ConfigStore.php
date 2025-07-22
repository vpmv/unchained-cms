<?php

namespace App\System\Configuration;

use App\System\Application\Field;
use App\System\Application\Property;
use App\System\Helpers\Timer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\NoConfigurationException;

class ConfigStore extends ConfigStoreBase
{
    public const string DIR_PUBLIC    = 'public';
    public const string DIR_FILES     = 'files';
    public const string DIR_IMAGES    = 'images';
    public const string DIR_EXTENSION = 'extension';

    protected $applicationConfig = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly Timer $timer,
        Security $security,
        private readonly string $projectDir,
        private readonly string $publicDir,
    ) {
        parent::__construct($this->projectDir);

        $this->basePath = $this->projectDir . '/user/config/';

        $this->paths         = [
            'root'   => $this->projectDir,
            'public' => $this->publicDir,
        ];
        $this->authenticated = !empty($security->getToken()?->getRoleNames());
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
                return new ApplicationConfig($this, $config, $appId, $this->projectDir);
            });
            $this->timer->stop('config.' . $appId);
        }
        $this->setApplicationSystemConfig($appId);

        return $this->applications[$appId];
    }


    /**
     * @param string $applicationId
     *
     * @throws \Symfony\Component\Routing\Exception\NoConfigurationException   Application not defined
     */
    private function setApplicationSystemConfig(string $applicationId): void
    {
        if (isset($this->applicationConfig[$applicationId])) {
            return;
        }

        $applications = $this->readSystemConfig('applications', 'applications');
        if (!array_key_exists($applicationId, $applications)) {
            throw new NoConfigurationException('Application not configured');
        }
        $userLocale = $this->requestStack->getMainRequest()->getLocale();

        $this->applicationConfig[$applicationId] = $this->remember('sysconfig.' . $applicationId . '.' . $userLocale . '-' . intval($this->authenticated),
            function () use ($applicationId, $applications, $userLocale) {
                $sysConfig = $applications[$applicationId];
                $appConfig = $this->applications[$applicationId];

                $routePrefix = null;
                if ($categoryId = $appConfig->getCategory()->getCategoryId()) {
                    try {
                        $categoryConfig = $this->getCategoryConfig($categoryId);
                        $routePrefix    = $categoryConfig->getRoute($userLocale, true);
                    } catch (NoConfigurationException) {
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
            } catch (NoConfigurationException) {
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