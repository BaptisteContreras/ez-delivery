<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Pr;

/**
 * Fetches open PRs for a repo, deciding what backs their delivery, and
 * (where supported) pushes label updates back after a release.
 *
 * How a Pr's Selector is derived (linked issue today; possibly the Pr
 * itself or an external tracker later) is entirely this driver's concern -
 * callers only ever see a Pr with its Selector already attached.
 */
interface RemoteRepoDriver
{
    public function support(ProjectRepoConfig $projectRepoConfig): bool;

    public function supportLabelsUpdate(): bool;

    /**
     * @param array<LabelsUpdate> $labelsUpdates
     */
    public function updateLabels(ProjectRepoConfig $projectRepoConfig, array $labelsUpdates): void;

    /**
     * @return array<Pr> each already carrying its Selector
     */
    public function getPrs(ProjectRepoConfig $projectRepoConfig): array;
}
