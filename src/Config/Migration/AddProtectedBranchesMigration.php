<?php

namespace Ezdeliver\Config\Migration;

use Symfony\Component\Console\Style\SymfonyStyle;

final class AddProtectedBranchesMigration implements ConfigMigration
{
    private const array DEFAULT_PROTECTED_BRANCHES = ['master', 'main', 'develop', 'integration'];

    public function getFromVersion(): int
    {
        return 5;
    }

    public function getToVersion(): int
    {
        return 6;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, SymfonyStyle $io): array
    {
        $config['protectedBranches'] ??= self::DEFAULT_PROTECTED_BRANCHES;
        $config['version'] = $this->getToVersion();

        return $config;
    }
}
