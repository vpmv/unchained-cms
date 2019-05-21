<?php

namespace App\System;

use App\System\Application\Category;
use App\System\Application\Property;
use App\System\Configuration\ConfigStore;
use App\System\Application\Application;
use App\System\Helpers\Timer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @package App\Applications
 */
class ApplicationManager
{
    /** @var \App\System\RepositoryManager|array */
    private $repositoryManager = [];
    /** @var array */
    private $applications = [];

    /** @var ContainerInterface */
    private $container;

    /** @var ConfigStore */
    private $configStore;

    /** @var \App\System\Helpers\Timer */
    private $timer;
    /** @var \Symfony\Component\Form\FormFactory */
    private $forms;
    /** @var \Symfony\Component\HttpFoundation\RequestStack */
    private $requestStack;

    /**
     * ApplicationManager constructor.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack            $requestStack
     * @param \App\System\RepositoryManager                             $repositoryManager
     * @param \App\System\Helpers\Timer                                 $timer
     * @param \Symfony\Component\Form\FormFactoryInterface              $formFactory
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \Symfony\Component\Translation\TranslatorInterface        $translator
     * @param \App\System\Configuration\ConfigStore                     $configStore
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function __construct(RequestStack $requestStack, RepositoryManager $repositoryManager, Timer $timer, FormFactoryInterface $formFactory, ContainerInterface $container, TranslatorInterface $translator, ConfigStore $configStore)
    {
        $this->timer = $timer;
        $this->timer->setCategory('factory.application');

        $this->requestStack      = $requestStack;
        $this->repositoryManager = $repositoryManager;
        $this->forms             = $formFactory;
        $this->translator        = $translator;
        $this->container         = $container;
        $this->configStore       = $configStore;;
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
     * @param string $appId
     * @param array  $extra
     *
     * @return \App\System\Application\Application
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
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
        };
        if (!file_exists($filesPath)) {
            mkdir($filesPath, 0777, true);
        };

        $app = new Application($appId, $this->container->get('request_stack'), $this->configStore, $this->repositoryManager->getRepository($appId), $this->getFormBuilder($appId), $this->translator);
        $this->loadApplicationExtension($appId);

        return $app;
    }

    /**
     * @param $path
     *
     * @return \App\System\Application\Application|null
     * @throws \LogicException
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function getApplicationByPath($path): ?Application
    {
        $this->configureApplications();
        $locale = $this->requestStack->getMasterRequest()->getLocale();
        foreach ($this->applications as $categoryId => $category) {
            $categoryRoute = $this->configStore->getCategoryUri($categoryId, $locale);
            if ($categoryRoute && preg_match('/^' . str_replace('/', '\/', $categoryRoute) . '(\/(charts))?$/', $path) && true === $category['visible']) {
                $_application = $this->getCategory($categoryId);
                $matchedRoute = $categoryRoute;
                break;
            }

            foreach ($category['applications'] as $appId => $application) {
                $appRoute = $this->configStore->getApplicationUri($appId, null, $locale);
                if (preg_match('/^' . str_replace('/', '\/', $appRoute) . '(\/(charts|detail|[\w\-]{2,}))?$/', $path) && true === $application['visible']) {
                    if (!$application['visible']) {
                        throw new NotFoundHttpException('Application is not public');
                    }

                    $_application = $this->getApplication($application['appId'], $categoryId);
                    $matchedRoute = $appRoute;
                    break 2;
                }
            }

        }

        if (empty($_application)) {
            throw new NotFoundHttpException('Application could not be found within the path. Wrong locale?');
        }

        $routeParams = array_filter(explode('/', ltrim(str_replace($matchedRoute, '', $path), '/')));
        $module      = null;
        if (!empty($routeParams)) {  // fixme
            if ($routeParams == 'charts') {
                $module      = 'charts';
                $routeParams = [];
            } else {
                $module = 'detail';
            }
        }
        $_application->boot($module);
        $_application->apply($routeParams ? ['_slug' => $routeParams[0]] : []); // fixme routeparams

        return $_application;
    }

    /**
     * @param bool $visibleOnly
     *
     * @return array
     */
    public function getApplications(bool $visibleOnly = false)
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

        return $categories;
    }

    public function getCategory(string $categoryId): Category
    {
        $this->configureApplications();

        $config = $this->applications[$categoryId] ?? null;
        if (!$config) {
            throw new NotFoundHttpException('Category does not exist');
        }
        $category = new Category($categoryId, $this->requestStack, $this->configStore, $this->translator);

        return $category;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function configureApplications(): void
    {
        if ($this->applications) {
            return;
        }
        $locale = $this->requestStack->getMasterRequest()->getLocale();

        $this->applications = [];
        $appConfig          = $this->configStore->readSystemConfig('applications');
        foreach ($appConfig['applications'] as $appId => $app) {
            try {
                $config = $this->configStore->getApplicationConfig($appId);
            } catch (NoConfigurationException $e) {
                continue;
            }

            $category = $config->getCategory();
            if (!isset($this->applications[$category->getCategoryId()])) {
                $this->applications[$category->getCategoryId()] = [
                    'label'        => $category->getLabel(),
                    'description'  => $category->getDescription(),
                    'route'        => $category->getRoute($locale),
                    'visible'      => $category->isVisible() || $this->isAuthorizedFully(),
                    'applications' => [],
                ];
            }

            $this->applications[$category->getCategoryId()]['applications'][$appId] = [
                'appId'              => $appId,
                'order'              => $app['order'] ?? 0,
                'public'             => $app['public'],
                'visible'            => $this->isAuthorizedFully() || (!$this->isAuthorizedFully() && ($app['public'] ?? true)),
                'config'             => $config,
                'route'              => $this->configStore->getApplicationUri($appId, null, $locale),
                'translation_domain' => Property::schemaName($appId),
            ];
        }
    }

    private function isAuthorizedFully(): bool
    {
        return !empty($this->container->get('security.token_storage')->getToken()->getRoles());
    }

    private function getFormBuilder(string $applicationId)
    {
        return $this->forms->createBuilder(FormType::class, [], [
            'translation_domain' => Property::schemaName($applicationId),
        ]);
    }
}