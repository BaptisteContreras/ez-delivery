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
        $driver = $this->selectDriver($projectRepoConfig);
        $prs = $driver->getPrs($projectRepoConfig);
        $referenceStrategy = $driver->getPrReferenceStrategy();

        /** @var array<Pr> $prsToDeliver */
        $prsToDeliver = array_filter($prs, function (Pr $pr) use ($selectedEnv, $referenceStrategy) {
            $labels = $referenceStrategy->resolveLabels($pr);
            $matches = in_array($selectedEnv->getToDeliverLabel(), $labels, true) || in_array($selectedEnv->getAlreadyDeliveredLabel(), $labels, true);

            $this->verbose(sprintf(
                'PR #%s "%s" %s: labels [%s] vs to-deliver "%s" / already-delivered "%s"',
                $pr->getId(),
                $pr->getTitle(),
                $matches ? 'INCLUDED' : 'excluded',
                implode(', ', $labels),
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
     *
     * @rw#4 I think we should rename and maybe add some quick doc about this method. It does not just update labels, it updates the labels that are used to select the PR
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $prsDelivered, ProjectEnvConfig $selectedEnv): void
    {
        $driver = $this->selectDriver($projectRepoConfig); // @rw#3 rename $driver to $remoteRepoDriver
        $referenceStrategy = $driver->getPrReferenceStrategy();
        $updateStrategy = $driver->getLabelsUpdateStrategy();

        $prsToUpdate = array_filter($prsDelivered, fn (Pr $pr) => in_array($selectedEnv->getToDeliverLabel(), $referenceStrategy->resolveLabels($pr), true));

        $labelUpdates = array_map(function (Pr $pr) use ($selectedEnv, $referenceStrategy, $updateStrategy) {
            $labels = array_filter($referenceStrategy->resolveLabels($pr), fn ($label) => $label !== $selectedEnv->getToDeliverLabel());
            $labels[] = $selectedEnv->getAlreadyDeliveredLabel();

            return $updateStrategy->buildUpdate($pr, array_values(array_unique($labels)));
        }, $prsToUpdate);

        $driver->updateLabels($projectRepoConfig, $labelUpdates);
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
