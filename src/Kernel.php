<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /** @var string */
    protected $publicDir;

    public function __construct(string $environment, bool $debug, string $publicDir)
    {
        parent::__construct($environment, $debug);
        $this->publicDir = $publicDir;
    }

    public function registerBundles()
    {
        $bundles = [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new \Symfony\Bundle\MonologBundle\MonologBundle(),
        ];
        
        if ($this->getEnvironment() == 'dev') {
            $bundles[] = new \Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new \Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
        }

        return $bundles;
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $c->setParameter('kernel.public_dir', $this->publicDir);

        $configDirs  = [
            __DIR__ . '/../config/packages',
            __DIR__ . '/../config/packages/' . $this->getEnvironment(),
            __DIR__ . '/../user/config/framework',
        ];

        $loader->load($configDirs[0].'/../framework.yaml');
        $loader->load($configDirs[0].'/../services.yaml');
        $loader->load($configDirs[0].'/doctrine.yaml');
        $loader->load($configDirs[0].'/security.yaml');
        $loader->load($configDirs[0].'/twig.yaml');

        $envConfig = Finder::create()->files()->in($configDirs[1])->name('*.yaml');
        foreach ($envConfig as $file) {
            $loader->load($file->getRealPath());
        }
        $userConfig = Finder::create()->files()->in($configDirs[2])->name('*.yaml');
        if ($userConfig->hasResults()) {
            foreach ($userConfig as $file) {
                $loader->load($file->getRealPath());
            }
        }

        // configure WebProfilerBundle only if the bundle is enabled
        if (isset($this->bundles['WebProfilerBundle'])) {
            $c->loadFromExtension('web_profiler', [
                'toolbar'             => true,
                'intercept_redirects' => false,
            ]);
            $c->loadFromExtension('framework', [
                'profiler' => ['only_exceptions' => false],
            ]);
        }
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        // import the WebProfilerRoutes, only if the bundle is enabled
        if (isset($this->bundles['WebProfilerBundle'])) {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml', '/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml', '/_profiler');
        }

        // load the annotation routes
        $routes->import(__DIR__ . '/../src/Controller/', '/', 'annotation');
        $routes->add('/logout', null, 'logout');
    }

    // optional, to use the standard Symfony cache directory
    public function getCacheDir()
    {
        return __DIR__ . '/../var/cache/' . $this->getEnvironment();
    }

    // optional, to use the standard Symfony logs directory
    public function getLogDir()
    {
        return __DIR__ . '/../var/log';
    }
}