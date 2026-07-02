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
        private readonly MigrationRunner $migrationRunner,
        private readonly SerializerInterface $serializer,
        private readonly SymfonyStyle $io,
    ) {
    }

    public function migrateProjectConfig(string $project): int
    {
        if (!$this->storageHandler->isProjectConfigExists($project)) {
            throw new ProjectConfigNotFoundException($project);
        }

        $currentVersion = $this->storageHandler->peekConfigVersion($project);

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

        $this->storageHandler->backupConfig($project);

        $configArray = $this->storageHandler->loadConfigAsArray($project);
        $migratedArray = $this->migrationRunner->migrate($configArray, $currentVersion, ProjectConfiguration::CURRENT_VERSION, $this->io);

        $projectConfiguration = $this->serializer->deserialize(json_encode($migratedArray), ProjectConfiguration::class, 'json');
        $this->storageHandler->saveConfig($projectConfiguration);

        $this->io->success(sprintf(
            'Project config upgraded from version %d to version %d.',
            $currentVersion,
            ProjectConfiguration::CURRENT_VERSION
        ));

        return self::RETURN_CODE_OK;
    }
}
