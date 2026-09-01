<?php

namespace Ezdeliver\Config\Model;

use Symfony\Component\Serializer\Attribute\Ignore;

class ProjectConfiguration
{
    public const int INITIAL_VERSION = 1;
    public const int CURRENT_VERSION = 6;

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
