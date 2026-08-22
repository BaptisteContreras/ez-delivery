<?php

namespace Ezdeliver\Config\Migration;

use Symfony\Component\Console\Style\SymfonyStyle;

final class RelocateToLocalDirMigration implements ConfigMigration
{
    public function getFromVersion(): int
    {
        return 2;
    }

    public function getToVersion(): int
    {
        return 3;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, SymfonyStyle $io): array
    {
        // The version bump is the only content change here — physically relocating the file
        // from the legacy root path into local/ is a storage-layer concern, handled by
        // Migrator, not something this array-in-array-out contract can express.
        $config['version'] = $this->getToVersion();

        return $config;
    }
}
