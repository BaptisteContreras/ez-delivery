<?php

namespace Ezdeliver\Config\Migration;

use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrationRunner
{
    /**
     * @param array<ConfigMigration> $migrations
     */
    public function __construct(
        private readonly array $migrations,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, int $fromVersion, int $targetVersion, SymfonyStyle $io): array
    {
        $currentVersion = $fromVersion;

        while ($currentVersion < $targetVersion) {
            $migration = $this->findMigration($currentVersion);
            $config = $migration->migrate($config, $io);
            $currentVersion = $migration->getToVersion();
        }

        return $config;
    }

    private function findMigration(int $fromVersion): ConfigMigration
    {
        foreach ($this->migrations as $migration) {
            if ($migration->getFromVersion() === $fromVersion) {
                return $migration;
            }
        }

        throw new MigrationNotFoundException($fromVersion);
    }
}
