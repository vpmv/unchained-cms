<?php

namespace App\System;

use App\System\Application\Database\Repository;
use App\System\Configuration\ConfigStore;
use App\System\Constructs\Cacheable;
use App\System\Helpers\Timer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RepositoryManager extends Cacheable
{
    private $repositories = [];

    public function __construct(
        private ConfigStore $configStore,
        private EntityManagerInterface $em,
        private Timer $timer,
        private TokenStorageInterface $tokenStorage,
    ) {
        parent::__construct('repoman.');
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
                new Repository($this, $this->em, $this->timer, $this->configStore, $this->configStore->getApplicationConfig($appId));
            }
        });
    }

    public function getRepository(string $applicationId): Repository
    {
        if (isset($this->repositories[$applicationId])) {
            return $this->repositories[$applicationId];
        }

        $this->repositories[$applicationId] = new Repository($this, $this->em, $this->timer, $this->configStore, $this->configStore->getApplicationConfig($applicationId));

        return $this->repositories[$applicationId];
    }

    public function isAuthorizedFully(): bool
    {
        return !empty($this->tokenStorage->getToken()?->getRoleNames());
    }
}