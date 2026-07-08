<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Pr;

interface RemoteRepoDriver
{
    public function support(ProjectRepoConfig $projectRepoConfig): bool;

    public function supportLabelsUpdate(): bool;

    /**
     * @param array<LabelsUpdate> $labelsUpdates
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $labelsUpdates): void;

    /**
     * @return array<Pr>
     */
    public function getPrs(ProjectRepoConfig $projectRepoConfig): array;

    public function getPrReferenceStrategy(): PrReferenceStrategy;

    public function getLabelsUpdateStrategy(): LabelsUpdateStrategy;
}
