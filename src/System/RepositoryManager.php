<?php

namespace App\System;

use App\System\Application\Database\Repository;
use App\System\Configuration\ConfigStore;
use App\System\Constructs\Cacheable;
use App\System\Helpers\Timer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RepositoryManager extends Cacheable
{
    /** @var \Symfony\Component\DependencyInjection\ContainerInterface */
    private $container;
    /** @var \App\System\Configuration\ConfigStore */
    private $configStore;
    /** @var \Doctrine\ORM\EntityManagerInterface  */
    private $em;
    /** @var \App\System\Helpers\Timer */
    private $timer;

    private $repositories = [];

    public function __construct(ContainerInterface $container, ConfigStore $configStore, EntityManagerInterface $entityManager, CacheInterface $cache, Timer $timer)
    {
        $this->container   = $container;
        $this->configStore = $configStore;
        $this->em          = $entityManager;
        $this->timer       = $timer;

        parent::__construct($cache, 'repoman.');
        $this->initialize();
    }

    /**
     * Ensures initialization of all application repositories
     *
     * Prevents SQL errors for JOIN statements where foreign data is required
     * when the tables haven't been initialized yet
     *
     * Because repositories are cached internally, subsequent requests are faster
     */
    private function initialize(): void
    {
        $this->remember('init', function () {
            $applications = $this->configStore->readSystemConfig('applications', 'applications');
            foreach ($applications as $appId => $application) {
                new Repository($this, $this->em, $this->cache, $this->timer, $this->configStore, $this->configStore->getApplicationConfig($appId));
            }
        });
    }

    public function getRepository(string $applicationId): Repository
    {
        if (isset($this->repositories[$applicationId])) {
            return $this->repositories[$applicationId];
        }

        $this->repositories[$applicationId] = new Repository($this, $this->em, $this->cache, $this->timer, $this->configStore, $this->configStore->getApplicationConfig($applicationId));

        return $this->repositories[$applicationId];
    }

    public function isAuthorizedFully(): bool
    {
        return !empty($this->container->get('security.token_storage')->getToken()->getRoles());
    }
}