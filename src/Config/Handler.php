<?php

namespace Ezdeliver\Config;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\StorageHandler as PackageStorageHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

class Handler
{

    public function __construct(
        private readonly StorageHandler $storageHandler,
        private readonly InteractiveBuilder $interactiveBuilder,

    )
    {
    }


    public function createProjectConfig(): ProjectConfiguration
    {
        $this->storageHandler->initConfigsDir();

        $projectConfig = $this->interactiveBuilder->buildConfig();

        $this->storageHandler->saveConfig($projectConfig);

        return $projectConfig;

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