<?php

namespace Ezdeliver;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class StorageHandler
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly Filesystem $fs,
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

    public function hasPausedDelivery(ProjectConfiguration $projectConfiguration): bool
    {
        return $this->fs->exists($this->getProjectLastReleaseStorageDir($projectConfiguration->getProjectName()));
    }

    private function getProjectTmpStorageDir(string $projectName): string
    {
        return sprintf('%s/%s', $this->configsDirPath, $projectName);
    }

    private function getProjectLastReleaseStorageDir(string $projectName): string
    {
        return sprintf('%s/last', $this->getProjectTmpStorageDir($projectName));
    }
}