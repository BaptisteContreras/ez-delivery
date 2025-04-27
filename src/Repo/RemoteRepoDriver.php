<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Pr;

interface RemoteRepoDriver
{
    public function support(ProjectRepoConfig $projectRepoConfig): bool;

    public function supportLabelsUpdate(): bool;

    /**
     * @param array<IssueLabelsUpdate> $issuesLabelsUpdates
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $issuesLabelsUpdates): void;

    /**
     * @return array<Pr>
     */
    public function getPrsWithLinkedIssue(ProjectRepoConfig $projectRepoConfig): array;
}
