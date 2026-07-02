<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Pr;
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
            $matches = $pr->hasLabel($selectedEnv->getToDeliverLabel()) || $pr->hasLabel($selectedEnv->getAlreadyDeliveredLabel());

            $this->verbose(sprintf(
                'PR #%s "%s" %s: labels [%s] vs to-deliver "%s" / already-delivered "%s"',
                $pr->getId(),
                $pr->getTitle(),
                $matches ? 'INCLUDED' : 'excluded',
                implode(', ', $pr->getLabels()),
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

    public function getPrReferenceStrategy(ProjectRepoConfig $projectRepoConfig): PrReferenceStrategy
    {
        return $this->selectDriver($projectRepoConfig)->getPrReferenceStrategy();
    }

    /**
     * @param array<Pr> $prsDelivered
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $prsDelivered, ProjectEnvConfig $selectedEnv): void
    {
        $prsToUpdate = array_filter($prsDelivered, fn (Pr $pr) => $pr->hasLabel($selectedEnv->getToDeliverLabel()));

        $labelUpdates = array_map(function (Pr $pr) use ($selectedEnv) {
            $labels = array_filter($pr->getLabels(), fn ($label) => $label !== $selectedEnv->getToDeliverLabel());
            $labels[] = $selectedEnv->getAlreadyDeliveredLabel();

            $reference = $pr->getReference();

            return new LabelsUpdate(
                $reference?->getId() ?? $pr->getId(),
                $reference?->getTitle() ?? $pr->getTitle(),
                array_values(array_unique($labels))
            );
        }, $prsToUpdate);

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
