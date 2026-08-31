<?php

namespace Ezdeliver\Config\Migration;

use Symfony\Component\Console\Style\SymfonyStyle;

final class AddBranchNamePatternMigration implements ConfigMigration
{
    private const string DEFAULT_PATTERN = '%env%-%date_time%';

    public function getFromVersion(): int
    {
        return 3;
    }

    public function getToVersion(): int
    {
        return 4;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, SymfonyStyle $io): array
    {
        foreach ($config['envs'] as &$env) {
            $env['branchNamePattern'] ??= self::DEFAULT_PATTERN;
        }
        unset($env);

        $config['version'] = $this->getToVersion();

        return $config;
    }
}
