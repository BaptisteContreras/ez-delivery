<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Pr;

class RemoteRepo
{

    /**
     * @param array<RemoteRepoDriver> $remoteRepoDrivers
     */
    public function __construct(
        private readonly array $remoteRepoDrivers
    )
    {
    }

    /**
     * @return array<Pr>
     */
    public function getPrsToDeliver(ProjectRepoConfig $projectRepoConfig, ProjectEnvConfig $selectedEnv): array
    {
        $prs = $this->selectDriver($projectRepoConfig)->getPrsWithLinkedIssue($projectRepoConfig);

        /** @var array<Pr> $prsToDeliver */
        $prsToDeliver = array_filter($prs, fn(Pr $pr) => $pr->hasClosingIssueWithLabel($selectedEnv->getToDeliverLabel()) || $pr->hasClosingIssueWithLabel($selectedEnv->getAlreadyDeliveredLabel()));

        return $prsToDeliver;
    }

    /**
     * @throws DriverNotFoundException
     */
    private function selectDriver(ProjectRepoConfig $projectRepoConfig): RemoteRepoDriver
    {
        foreach ($this->remoteRepoDrivers as $driver) {
            if ($driver->support($projectRepoConfig))
            {
                return $driver;
            }
        }

        throw new DriverNotFoundException();
    }
}