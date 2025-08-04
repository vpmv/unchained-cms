<?php

namespace App\System;

use App\System\Application\Database\Repository;
use App\System\Configuration\ConfigStore;
use App\System\Constructs\Cacheable;
use App\System\Helpers\Timer;
use Doctrine\ORM\EntityManagerInterface;

class RepositoryManager extends Cacheable
{
    private array $repositories = [];

    public function __construct(
        private readonly ConfigStore $configStore,
        private readonly EntityManagerInterface $em,
        private readonly Timer $timer,
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
            $this->configStore->configureApplications();

            $applications = $this->configStore->getApplications();
            foreach ($applications as $appId => $application) {
                new Repository($this, $this->em, $this->timer, $this->configStore, $application);
            }
        });
    }

    public function getRepository(string $applicationId): Repository
    {
        if (isset($this->repositories[$applicationId])) {
            return $this->repositories[$applicationId];
        }

        $this->repositories[$applicationId] = new Repository($this, $this->em, $this->timer, $this->configStore, $this->configStore->getApplication($applicationId));

        return $this->repositories[$applicationId];
    }
}