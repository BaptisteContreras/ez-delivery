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
        private readonly ?StorageHandler $legacyStorageHandler = null,
        private readonly ?Migrator $migrator = null,
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
        return $this->resolveStorageHandlerForRead($project)->peekConfigVersion($project);
    }

    /**
     * @throws ProjectConfigNotFoundException
     */
    public function loadProjectConfig(string $project): ProjectConfiguration
    {
        return $this->resolveStorageHandlerForRead($project)->loadConfig($project);
    }

    /**
     * Resolves the handler to read $project from, migrating it out of the legacy
     * location first when needed — so the two callers above never read stale data.
     *
     * @throws ProjectConfigNotFoundException
     */
    private function resolveStorageHandlerForRead(string $project): StorageHandler
    {
        if ($this->storageHandler->isProjectConfigExists($project)) {
            return $this->storageHandler;
        }

        if (null === $this->legacyStorageHandler || !$this->legacyStorageHandler->isProjectConfigExists($project)) {
            throw new ProjectConfigNotFoundException($project);
        }

        if (null === $this->migrator) {
            return $this->legacyStorageHandler;
        }

        $this->migrator->migrateProjectConfig($project);

        return $this->storageHandler;
    }
}
