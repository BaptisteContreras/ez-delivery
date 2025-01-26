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
    )
    {
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

    private function getProjectConfigFilePath(string $projectName): string
    {
        return sprintf('%s/%s.json', $this->configsDirPath, $projectName);
    }


}