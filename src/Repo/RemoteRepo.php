<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Selector;
use Symfony\Component\Console\Style\SymfonyStyle;

class RemoteRepo
{
    /**
     * @param array<RemoteRepoDriver> $remoteRepoDrivers
     */
    public function __construct(
        private readonly array $remoteRepoDrivers,
        private readonly SymfonyStyle $io,
    ) {
    }

    /**
     * @return array<Pr>
     */
    public function getPrsToDeliver(ProjectRepoConfig $projectRepoConfig, ProjectEnvConfig $selectedEnv): array
    {
        $prs = $this->selectDriver($projectRepoConfig)->getPrs($projectRepoConfig);

        /** @var array<Pr> $prsToDeliver */
        $prsToDeliver = array_filter($prs, function (Pr $pr) use ($selectedEnv) {
            $matches = $pr->getSelector()->hasLabel($selectedEnv->getToDeliverLabel()) || $pr->getSelector()->hasLabel($selectedEnv->getAlreadyDeliveredLabel());

            $this->verbose(sprintf(
                'PR #%s (issue #%s "%s") %s: labels [%s] vs to-deliver "%s" / already-delivered "%s"',
                $pr->getId(),
                $pr->getSelectorId(),
                $pr->getSelectorTitle(),
                $matches ? 'INCLUDED' : 'excluded',
                implode(', ', $pr->getSelectorLabels()),
                $selectedEnv->getToDeliverLabel(),
                $selectedEnv->getAlreadyDeliveredLabel()
            ));

            return $matches;
        });

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
        $selectorsToUpdate = array_filter(
            array_map(fn (Pr $pr) => $pr->getSelector(), $prsDelivered),
            fn (Selector $selector) => $selector->hasLabel($selectedEnv->getToDeliverLabel())
        );

        $labelUpdates = array_map(function (Selector $selector) use ($selectedEnv) {
            $labels = $selector->getLabels();

            $labels = array_filter($labels, fn ($label) => $label !== $selectedEnv->getToDeliverLabel());

            $labels[] = $selectedEnv->getAlreadyDeliveredLabel();

            return new LabelsUpdate($selector->getId(), $selector->getTitle(), array_values(array_unique($labels)));
        }, $selectorsToUpdate);

        $this->selectDriver($projectRepoConfig)->updateLabels($projectRepoConfig, $labelUpdates);
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

    private function verbose(string $line): void
    {
        if ($this->io->isVerbose()) {
            $this->io->comment($line);
        }
    }
}
