<?php

namespace App\System;

use App\System\Application\Database\Repository;
use App\System\Configuration\ConfigStore;
use App\System\Constructs\Cacheable;
use App\System\Helpers\Timer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class RepositoryManager extends Cacheable
{
    private array $repositories = [];

    public function __construct(
        private readonly ConfigStore $configStore,
        private readonly EntityManagerInterface $em,
        private readonly Timer $timer,
        private readonly Security $security,
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
        return !empty($this->security->getToken()?->getRoleNames());
    }
}