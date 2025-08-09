<?php

namespace App\System\Configuration;

use App\System\Application\Property;
use App\System\Configuration\Exception\SequenceException;
use App\System\Router;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\NoConfigurationException;

class ConfigStore extends ConfigStoreBase
{
    public const string DIR_PUBLIC    = 'public';
    public const string DIR_FILES     = 'files';
    public const string DIR_IMAGES    = 'images';
    public const string DIR_EXTENSION = 'extension';

    protected $paths = [];

    /** @var \App\System\Configuration\ApplicationConfig[] */
    protected $applications = [];

    /** @var ApplicationCategory[] */
    protected        $categories = [];
    protected string $locale;


    public function __construct(
        public readonly Router $router,
        private readonly RequestStack $requestStack,
        //private readonly LoggerInterface $logger,
        //private readonly Timer $timer,
        private Security $security,
        protected readonly string $projectDir,
        private readonly string $publicDir,
    ) {
        parent::__construct($this->projectDir);

        $this->basePath = $this->projectDir . '/user/config/';
        $this->paths    = [
            'root'   => $this->projectDir,
            'public' => $this->publicDir,
        ];

        $this->locale = $this->requestStack->getMainRequest()->getLocale();
    }

    public function isAuthenticated(): bool
    {
        return !empty($this->security->getToken()?->getRoleNames());
    }

    public function configureApplications(): void
    {
        $config       = $this->readSystemConfig('applications');
        $applications = $config['applications'];
        $categories   = ($config['categories'] ?? []) + ['_default' => []];

        foreach ($categories as $categoryId => $category) {
            $this->categories[$categoryId] = $this->remember('category.' . $categoryId, function () use ($categoryId, $category) {
                return new ApplicationCategory($categoryId, $category);
            });
        }

        foreach ($applications as $appId => $appConfig) {
            $this->getApplication($appId, $appConfig); // populate $this->applications[$appId]
            $this->router->addRoutes(...
                $this->remember("routes.app.$appId", function () use ($appId) {
                    return Route::create($this->applications[$appId]);
                }),
            );
        }
    }

    /**
     * @return \App\System\Configuration\ApplicationConfig[]
     */
    public function getApplications(): array
    {
        return $this->applications;
    }

    /**
     * @param string $appId
     *
     * @return \App\System\Configuration\ApplicationConfig
     */
    public function getApplication(string $appId, ?array $appConfig = null): ApplicationConfig
    {
        if (!isset($this->applications[$appId])) {
            $this->applications[$appId] = $this->remember("application.$appId", function () use ($appId, $appConfig) {
                $appConfig ??= $this->readSystemConfig('applications', 'applications')[$appId];

                $config = $this->readYamlFile('applications/' . $appId . '.yaml');
                if (empty($config['application'])) {
                    throw new NoConfigurationException('No configuration found for App<' . $appId . '>');
                }

                foreach (static::BASE_KEYS as $key) {
                    if (array_key_exists($key, $appConfig ?? [])) {
                        $config['application'][$key] = $appConfig[$key];
                    }
                }

                /** @var \App\System\Configuration\ApplicationCategory $category */
                $category = $this->categories[$config['application']['category'] ?? '_default'];

                return new ApplicationConfig($this, $category, $config['application'], $appId, $this->projectDir);
            });
        }

        return $this->applications[$appId];
    }

    /**
     * @param string $categoryId
     *
     * @return \App\System\Configuration\ApplicationCategory
     */
    public function getCategoryConfig(string $categoryId): ApplicationCategory
    {
        if (!isset($this->categories[$categoryId])) {
            throw new SequenceException('unconfigured category');
        }

        return $this->categories[$categoryId];
    }

    public function getApplicationRoute(string $applicationId, ?string $sourceAlias = null): Route
    {
        $config = $this->getApplication($applicationId);
        if ($sourceAlias) {
            $applicationId = $config->getSourceId($sourceAlias);
        }
        $config = $this->getApplication($applicationId);

        return $this->router->matchApp($config->getCategory()->getCategoryId(), $applicationId);
    }

    public function getDirectory(string $type, string $applicationId, ?string $sourceAlias = null, bool $asFileSystem = false): string
    {
        if ($type == static::DIR_PUBLIC) {
            return $this->paths['public'];
        } elseif ($type == static::DIR_EXTENSION) {
            return $this->paths['root'] . '/user/extensions';
        }

        $config = $this->getApplication($applicationId);
        if ($sourceAlias) {
            $config = $config->getSourceConfig($sourceAlias);
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
        $config = $this->getApplication($applicationId);
        if ($sourceAlias) {
            $config = $config->getSourceConfig($sourceAlias);
        }

        return Property::foreignKey($config->appId);
    }

}