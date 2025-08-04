<?php

namespace App\System;

use App\System\Application\Application;
use App\System\Application\Category;
use App\System\Application\Property;
use App\System\Configuration\ApplicationType;
use App\System\Configuration\ConfigStore;
use App\System\Configuration\Route;
use App\System\Constructs\Cacheable;
use App\System\Helpers\Timer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @package App\Applications
 */
class ApplicationManager extends Cacheable
{
    /** @var array Categorized listing of applications */
    private array  $applications = [];
    public Application|Category $activeApp;

    /**
     * @param \Symfony\Component\HttpFoundation\RequestStack     $requestStack
     * @param \App\System\RepositoryManager                      $repositoryManager
     * @param \App\System\Helpers\Timer                          $timer
     * @param \Symfony\Component\Form\FormFactoryInterface       $forms
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator
     * @param \App\System\Configuration\ConfigStore              $configStore
     * @param \Symfony\Bundle\SecurityBundle\Security            $security
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RepositoryManager $repositoryManager,
        private readonly Timer $timer,
        private readonly FormFactoryInterface $forms,
        private readonly TranslatorInterface $translator,
        private readonly ConfigStore $configStore,
        private readonly Security $security,
    ) {
        parent::__construct('appman.');
        $this->timer->setCategory('factory.application');
    }

    /**
     * Configures applications for current locale, nested within categories
     *
     * This configuration is used for dashboards
     *
     * @return void
     */
    protected function configureApplications(): void
    {
        if ($this->applications) {
            return;
        }
        $locale = $this->requestStack->getMainRequest()->getLocale() ?: $_ENV('LOCALE') ?: 'en';

        $cacheKey           = ['applications', $locale, $this->isAuthorizedFully() ? 'auth' : 'pub'];
        $this->applications = $this->remember(implode('.', $cacheKey), function () use ($locale) {
            $applications = [];
            $baseApps     = $this->configStore->getApplications();
            foreach ($baseApps as $appId => $app) {
                try {
                    $config = $this->configStore->getApplication($appId);
                } catch (NoConfigurationException) {
                    continue;
                }

                $category = $config->getCategory();
                if (!isset($applications[$category->getCategoryId()])) {
                    $applications[$category->getCategoryId()] = [
                        'label'        => $category->getLabel(),
                        'description'  => $category->getDescription(),
                        'route'        => $this->configStore->router->matchApp($category->getCategoryId()),
                        'visible'      => $category->isVisible() || $this->isAuthorizedFully(),
                        'applications' => [],
                    ];
                }

                $applications[$category->getCategoryId()]['applications'][$appId] = [
                    'appId'              => $appId,
                    'order'              => $app->getConfig('order', 0),
                    'public'             => $app->isPublic(),
                    'visible'            => $this->isAuthorizedFully() || (!$this->isAuthorizedFully() && ($app->isPublic())),
                    'config'             => $config,
                    'route'              => $this->configStore->router->matchApp($category->getCategoryId(), $appId),
                    'translation_domain' => Property::schemaName($appId),
                    'entry_count'        => -1,
                ];
            }

            return $applications;
        });
    }

    /**
     * @param string $appId
     * @param string $categoryId
     *
     * @return \App\System\Application\Application
     */
    public function getApplication(string $appId, string $categoryId = '_default'): Application
    {
        $this->configureApplications();

        $category    = $this->applications[$categoryId] ?? [];
        $application = $this->applications[$categoryId]['applications'][$appId] ?? [];
        if (!$application) {
            throw new NotFoundHttpException('Application does not exist');
        }
        if (false === ($category['visible'] ?? false) || false === ($application['visible'] ?? false)) {
            throw new NotFoundHttpException('Application not authorized');
        }

        $imagesPath = $this->configStore->getDirectory(ConfigStore::DIR_IMAGES, $appId, null, true);
        $filesPath  = $this->configStore->getDirectory(ConfigStore::DIR_FILES, $appId, null, true);
        if (!file_exists($imagesPath)) {
            mkdir($imagesPath, 0777, true);
        }

        if (!file_exists($filesPath)) {
            mkdir($filesPath, 0777, true);
        }

        $app = new Application($appId, $this->requestStack, $this->configStore, $this->repositoryManager->getRepository($appId), $this->getFormBuilder($appId), $this->translator);
        $this->loadApplicationExtension($appId);

        return $app;
    }

    /**
     * @param bool $visibleOnly
     *
     * @return array Categories;
     *               If application is uncategorized, it's placed under _default
     */
    public function getApplications(bool $visibleOnly = false): array
    {
        $this->configureApplications();

        $categories = $this->applications;
        if ($visibleOnly) {
            $categories = array_filter($categories, function ($cat) {
                return $cat['visible'] === true;
            });
        }
        foreach ($categories as &$category) {
            uasort($category['applications'], function ($a, $b) {
                return $a['order'] <=> $b['order'];
            });
            if ($visibleOnly) {
                $category['applications'] = array_filter($category['applications'], function ($app) {
                    return $app['visible'] === true;
                });
            }
        }
        $categories = array_filter($categories, function ($cat) {
            return !!$cat['applications'];
        });

        return $categories;
    }

    public function getCategory(string $categoryId): Category
    {
        $this->configureApplications();

        $config = $this->applications[$categoryId] ?? null;
        if (!$config) {
            throw new NotFoundHttpException('Category does not exist');
        }
        return new Category($categoryId, $this->requestStack, $this->configStore, $this->translator);
    }

    /**
     * @param string $appId
     *
     * @throws \InvalidArgumentException
     */
    public function loadApplicationExtension(string $appId): void
    {
        $appPath = $this->configStore->getDirectory(ConfigStore::DIR_EXTENSION, $appId);
        $app     = $appPath . '/' . Property::schemaName($appId) . '.php';
        if (file_exists($app)) {
            require_once $app;
        }
    }

    /**
     * @param \App\System\Configuration\Route $route
     * @param string                          $module
     *
     * @return array
     */
    public function runApplication(Route $route): array
    {
        if ($route->applicationType == ApplicationType::Category) {
            $this->activeApp = $this->getCategory($route->getAppId());
        } else {
            $this->activeApp = $this->getApplication($route->getAppId(), $route->getCategoryId()); // category should always be _default in this case
        }

        $module = 'dashboard';
        $params = $route->getParams(true);
        if ($params['slug'] ?? false) {
            $module = 'detail';
            $params = ['_slug' => $params['slug']];
        }

        $this->activeApp->boot($module);
        $this->activeApp->apply($params);
        $data = $this->activeApp->run();
        if (null === $data) {
            throw new BadRequestHttpException('Not accepted');
        }

        return $data;
    }

    public function addRecordCount(array &$applications, ?string $onlyCategoryId = null): array
    {
        foreach ($applications as $categoryId => &$category) {
            if ($onlyCategoryId && $onlyCategoryId != $categoryId) {
                continue;
            }
            foreach ($category['applications'] as $appId => &$app) {
                $app['entry_count'] = $this->getApplication($appId, $categoryId)->getRepository()->getCount('', null);
            }
        }

        return $this->applications;
    }

    private function isAuthorizedFully(): bool
    {
        return !empty($this->security->getToken()?->getRoleNames());
    }

    private function getFormBuilder(string $applicationId): \Symfony\Component\Form\FormBuilderInterface
    {
        return $this->forms->createBuilder(FormType::class, [], [
            'translation_domain' => Property::schemaName($applicationId),
        ]);
    }

}