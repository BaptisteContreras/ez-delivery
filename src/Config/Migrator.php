<?php

namespace Ezdeliver\Config;

use Ezdeliver\Config\Migration\MigrationRunner;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;

class Migrator
{
    private const int RETURN_CODE_OK = 0;
    private const int RETURN_CODE_ERROR = 1;

    public function __construct(
        private readonly StorageHandler $storageHandler,
        private readonly StorageHandler $legacyStorageHandler,
        private readonly MigrationRunner $migrationRunner,
        private readonly SerializerInterface $serializer,
        private readonly SymfonyStyle $io,
    ) {
    }

    public function migrateProjectConfig(string $project): int
    {
        $sourceStorageHandler = $this->resolveSourceStorageHandler($project);

        return $this->migrate(
            $project,
            $sourceStorageHandler,
            $this->storageHandler,
            backup: true,
            deleteSourceAfterSave: $sourceStorageHandler === $this->legacyStorageHandler,
        );
    }

    /**
     * Upgrades a config inside the given storage, writing it back where it was read from.
     * No backup is written: this is used on the remote config clone, where git already
     * keeps the previous version and a stray `.bak` file would end up in the repo.
     *
     * @throws ProjectConfigNotFoundException
     */
    public function migrateProjectConfigInPlace(StorageHandler $storageHandler, string $project): int
    {
        if (!$storageHandler->isProjectConfigExists($project)) {
            throw new ProjectConfigNotFoundException($project);
        }

        // StorageHandler::saveConfig() names the file after the config's projectName, not
        // after the file it was read from: a mismatch would write a second, uncommitted file.
        $declaredName = $storageHandler->loadConfigAsArray($project)['projectName'] ?? null;

        if ($declaredName !== $project) {
            $this->io->error(sprintf(
                'Config file "%s.json" declares projectName "%s". Make them match before upgrading.',
                $project,
                (string) $declaredName
            ));

            return self::RETURN_CODE_ERROR;
        }

        return $this->migrate($project, $storageHandler, $storageHandler, backup: false);
    }

    private function migrate(
        string $project,
        StorageHandler $sourceStorageHandler,
        StorageHandler $targetStorageHandler,
        bool $backup,
        bool $deleteSourceAfterSave = false,
    ): int {
        $currentVersion = $sourceStorageHandler->peekConfigVersion($project);

        if (ProjectConfiguration::CURRENT_VERSION === $currentVersion) {
            $this->io->info(sprintf('Project config is already at version %d, nothing to do.', $currentVersion));

            return self::RETURN_CODE_OK;
        }

        if ($currentVersion > ProjectConfiguration::CURRENT_VERSION) {
            $this->io->error(sprintf(
                'Project config is at version %d, which is newer than this tool supports (version %d). Please update ez-delivery.',
                $currentVersion,
                ProjectConfiguration::CURRENT_VERSION
            ));

            return self::RETURN_CODE_ERROR;
        }

        $configArray = $sourceStorageHandler->loadConfigAsArray($project);

        if ($backup) {
            $sourceStorageHandler->backupConfig($project);
        }

        $migratedArray = $this->migrationRunner->migrate($configArray, $currentVersion, ProjectConfiguration::CURRENT_VERSION, $this->io);

        $projectConfiguration = $this->serializer->deserialize(json_encode($migratedArray), ProjectConfiguration::class, 'json');
        $targetStorageHandler->saveConfig($projectConfiguration);

        if ($deleteSourceAfterSave) {
            $sourceStorageHandler->deleteConfig($project);
            $sourceStorageHandler->deleteBackup($project);
        }

        $this->io->success(sprintf(
            'Project config upgraded from version %d to version %d.',
            $currentVersion,
            ProjectConfiguration::CURRENT_VERSION
        ));

        return self::RETURN_CODE_OK;
    }

    /**
     * @throws ProjectConfigNotFoundException
     */
    private function resolveSourceStorageHandler(string $project): StorageHandler
    {
        if ($this->storageHandler->isProjectConfigExists($project)) {
            return $this->storageHandler;
        }

        if ($this->legacyStorageHandler->isProjectConfigExists($project)) {
            return $this->legacyStorageHandler;
        }

        throw new ProjectConfigNotFoundException($project);
    }
}
