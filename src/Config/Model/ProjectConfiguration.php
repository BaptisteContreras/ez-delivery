<?php

namespace Ezdeliver\Config\Model;

use Symfony\Component\Serializer\Attribute\Ignore;

class ProjectConfiguration
{
    public const int INITIAL_VERSION = 1;
    public const int CURRENT_VERSION = 7;
    public const string DEFAULT_MERGE_ORDER_LABEL_PREFIX = 'merge-order::';

    /**
     * @param array<ProjectEnvConfig> $envs
     * @param array<string>           $protectedBranches
     */
    public function __construct(
        private readonly string $projectName,
        private readonly string $src,
        private readonly string $baseBranch,
        private readonly ProjectRepoConfig $repo,
        private array $envs,
        private readonly array $protectedBranches,
        private readonly int $version = self::INITIAL_VERSION,
        private readonly string $mergeOrderLabelPrefix = self::DEFAULT_MERGE_ORDER_LABEL_PREFIX,
        private readonly bool $enableSortMr = false,
    ) {
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function getBaseBranch(): string
    {
        return $this->baseBranch;
    }

    public function getRepo(): ProjectRepoConfig
    {
        return $this->repo;
    }

    /**
     * @return array<ProjectEnvConfig>
     */
    public function getEnvs(): array
    {
        return $this->envs;
    }

    /**
     * @return array<string>
     */
    public function getProtectedBranches(): array
    {
        return $this->protectedBranches;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getMergeOrderLabelPrefix(): string
    {
        return $this->mergeOrderLabelPrefix;
    }

    public function isEnableSortMr(): bool
    {
        return $this->enableSortMr;
    }

    #[Ignore]
    public function getEnv(string $name): ProjectEnvConfig
    {
        $env = current(array_filter($this->envs, fn (ProjectEnvConfig $env) => $env->getName() === $name));

        if (!$env instanceof ProjectEnvConfig) {
            throw new \Exception(sprintf('env "%s" not found', $name));
        }

        return $env;
    }
}
