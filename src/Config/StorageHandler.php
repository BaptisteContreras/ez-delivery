<?php

namespace Ezdeliver\Config;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

class StorageHandler
{
    private const string STORAGE_FORMAT = 'json';

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly Filesystem $fs,
        private readonly SerializerInterface $serializer,
        private readonly string $configsDirPath,
    ) {
    }

    public function initConfigsDir(): void
    {
        if (!$this->fs->exists($this->configsDirPath)) {
            $this->fs->mkdir($this->configsDirPath);
            $this->io->success(sprintf('%s config dir created', $this->configsDirPath));
        }
    }

    public function isProjectConfigExists(string $projectName): bool
    {
        return $this->fs->exists($this->getProjectConfigFilePath($projectName));
    }

    public function saveConfig(ProjectConfiguration $projectConfiguration): void
    {
        $configFilePath = $this->getProjectConfigFilePath($projectConfiguration->getProjectName());

        $this->fs->dumpFile($configFilePath, $this->serializer->serialize($projectConfiguration, self::STORAGE_FORMAT));

        $this->io->success(sprintf(
            'new project config %s stored at %s',
            $projectConfiguration->getProjectName(),
            $configFilePath
        ));
    }

    public function loadConfig(string $projectName): ProjectConfiguration
    {
        $rawData = file_get_contents($this->getProjectConfigFilePath($projectName));

        return $this->serializer->deserialize($rawData, ProjectConfiguration::class, self::STORAGE_FORMAT);
    }

    public function peekConfigVersion(string $projectName): int
    {
        $rawData = json_decode($this->readConfigFile($this->getProjectConfigFilePath($projectName)), true);

        return $rawData['version'] ?? ProjectConfiguration::INITIAL_VERSION;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadConfigAsArray(string $projectName): array
    {
        return json_decode($this->readConfigFile($this->getProjectConfigFilePath($projectName)), true);
    }

    public function backupConfig(string $projectName): void
    {
        $configPath = $this->getProjectConfigFilePath($projectName);
        $backupPath = sprintf('%s.bak', $configPath);

        $this->fs->copy($configPath, $backupPath, true);

        $this->io->success(sprintf('Backed up config to %s', $backupPath));
    }

    private function getProjectConfigFilePath(string $projectName): string
    {
        return sprintf('%s/%s.json', $this->configsDirPath, $projectName);
    }

    private function readConfigFile(string $filePath): string
    {
        $content = file_get_contents($filePath);

        if (false === $content) {
            throw new \RuntimeException(sprintf('Unable to read config file %s', $filePath));
        }

        return $content;
    }
}
