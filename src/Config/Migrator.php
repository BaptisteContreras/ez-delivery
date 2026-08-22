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

        $sourceStorageHandler->backupConfig($project);

        $configArray = $sourceStorageHandler->loadConfigAsArray($project);
        $migratedArray = $this->migrationRunner->migrate($configArray, $currentVersion, ProjectConfiguration::CURRENT_VERSION, $this->io);

        $projectConfiguration = $this->serializer->deserialize(json_encode($migratedArray), ProjectConfiguration::class, 'json');
        $this->storageHandler->saveConfig($projectConfiguration);

        if ($sourceStorageHandler === $this->legacyStorageHandler) {
            $this->legacyStorageHandler->deleteConfig($project);
            $this->legacyStorageHandler->deleteBackup($project);
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
