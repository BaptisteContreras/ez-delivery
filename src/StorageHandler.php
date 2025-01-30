<?php

namespace Ezdeliver;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Model\Release;
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

    public function createProjectTmpStorageDir(ProjectConfiguration $projectConfiguration): self
    {
        $tmpDirPath = $this->getProjectTmpStorageDir($projectConfiguration->getProjectName());

        if (!$this->fs->exists($tmpDirPath)) {
            $this->fs->mkdir($tmpDirPath);

            $this->io->success(sprintf('%s project temporary storage dir created', $tmpDirPath));
        }

        return $this;
    }

    public function storeRelease(Release $release, ProjectConfiguration $projectConfiguration): string
    {
        $storagePath = $this->getProjectLastReleaseStorageDir($projectConfiguration->getProjectName());
        $this->fs->dumpFile($storagePath, $this->serializer->serialize($release, self::STORAGE_FORMAT));

        return $storagePath;
    }

    public function hasPausedDelivery(ProjectConfiguration $projectConfiguration): bool
    {
        return $this->fs->exists($this->getProjectLastReleaseStorageDir($projectConfiguration->getProjectName()));
    }

    private function getProjectLastReleaseStorageDir(string $projectName): string
    {
        return sprintf('%s/last.json', $this->getProjectTmpStorageDir($projectName));
    }

    private function getProjectTmpStorageDir(string $projectName): string
    {
        return sprintf('%s/%s', $this->configsDirPath, $projectName);
    }


}