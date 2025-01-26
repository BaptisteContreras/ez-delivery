<?php

namespace Ezdeliver\Repo;

use Ezdeliver\Config\Model\GithubRepoConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;

class GithubDriver implements RemoteRepoDriver
{

    public function support(ProjectRepoConfig $projectRepoConfig): bool
    {
        return $projectRepoConfig instanceof GithubRepoConfig;
    }

    /**
     * @param GithubRepoConfig $projectRepoConfig
     */
    public function getPrsWithLinkedIssue(ProjectRepoConfig $projectRepoConfig): array
    {
        // TODO: Implement getPrsWithLinkedIssue() method.
    }
}