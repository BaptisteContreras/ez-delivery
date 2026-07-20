<?php

namespace Ezdeliver\Config;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Token\TokenVault;

class Handler
{
    public function __construct(
        private readonly StorageHandler $storageHandler,
        private readonly InteractiveBuilder $interactiveBuilder,
        private readonly TokenVault $tokenVault,
    ) {
    }

    public function setToken(string $ref, string $token): void
    {
        $this->tokenVault->set($ref, $token);
    }

    public function createProjectConfig(): ProjectConfiguration
    {
        $this->storageHandler->initConfigsDir();

        $projectConfig = $this->interactiveBuilder->buildConfig();

        $this->storageHandler->saveConfig($projectConfig);

        return $projectConfig;
    }

    public function peekProjectConfigVersion(string $project): int
    {
        return $this->storageHandler->peekConfigVersion($project);
    }

    /**
     * @throws ProjectConfigNotFoundException
     */
    public function loadProjectConfig(string $project): ProjectConfiguration
    {
        if (!$this->storageHandler->isProjectConfigExists($project)) {
            throw new ProjectConfigNotFoundException($project);
        }

        return $this->storageHandler->loadConfig($project);
    }
}
