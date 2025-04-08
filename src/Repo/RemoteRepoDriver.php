<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Pr;

interface RemoteRepoDriver
{
    public function support(ProjectRepoConfig $projectRepoConfig): bool;

    /**
     * @return array<Pr>
     */
    public function getPrsWithLinkedIssue(ProjectRepoConfig $projectRepoConfig): array;
}
