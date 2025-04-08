<?php

namespace Ezdeliver\Config\Model;

use Symfony\Component\Serializer\Attribute\Ignore;

class ProjectConfiguration
{
    /**
     * @param array<ProjectEnvConfig> $envs
     */
    public function __construct(
        private readonly string $projectName,
        private readonly string $src,
        private readonly string $baseBranch,
        private readonly ProjectRepoConfig $repo,
        private array $envs,
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
