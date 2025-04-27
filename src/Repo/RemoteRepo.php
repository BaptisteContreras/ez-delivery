<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;

class RemoteRepo
{
    /**
     * @param array<RemoteRepoDriver> $remoteRepoDrivers
     */
    public function __construct(
        private readonly array $remoteRepoDrivers,
    ) {
    }

    /**
     * @return array<Pr>
     */
    public function getPrsToDeliver(ProjectRepoConfig $projectRepoConfig, ProjectEnvConfig $selectedEnv): array
    {
        $prs = $this->selectDriver($projectRepoConfig)->getPrsWithLinkedIssue($projectRepoConfig);

        /** @var array<Pr> $prsToDeliver */
        $prsToDeliver = array_filter($prs, fn (Pr $pr) => $pr->hasClosingIssueWithLabel($selectedEnv->getToDeliverLabel()) || $pr->hasClosingIssueWithLabel($selectedEnv->getAlreadyDeliveredLabel()));

        return $prsToDeliver;
    }

    public function supportLabelsUpdate(ProjectRepoConfig $projectRepoConfig): bool
    {
        return $this->selectDriver($projectRepoConfig)->supportLabelsUpdate();
    }

    /**
     * @param array<Pr> $prsDelivered
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $prsDelivered, ProjectEnvConfig $selectedEnv): void
    {
        $issuesToUpdate = array_filter(
            array_map(fn (Pr $pr) => $pr->getClosingIssue(), $prsDelivered),
            fn (Issue $issue) => $issue->hasLabel($selectedEnv->getToDeliverLabel())
        );

        $issueLabelUpdates = array_map(function (Issue $issue) use ($selectedEnv) {
            $labels = $issue->getLabels();

            $labels = array_filter($labels, fn ($label) => $label !== $selectedEnv->getToDeliverLabel());

            $labels[] = $selectedEnv->getAlreadyDeliveredLabel();

            return new IssueLabelsUpdate($issue->getId(), $issue->getTitle(), array_values(array_unique($labels)));
        }, $issuesToUpdate);

        $this->selectDriver($projectRepoConfig)->updateLabels($projectRepoConfig, $issueLabelUpdates);
    }

    /**
     * @throws DriverNotFoundException
     */
    private function selectDriver(ProjectRepoConfig $projectRepoConfig): RemoteRepoDriver
    {
        foreach ($this->remoteRepoDrivers as $driver) {
            if ($driver->support($projectRepoConfig)) {
                return $driver;
            }
        }

        throw new DriverNotFoundException();
    }
}
