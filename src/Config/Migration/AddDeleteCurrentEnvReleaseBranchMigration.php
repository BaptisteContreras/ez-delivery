<?php

namespace Ezdeliver\Config\Migration;

use Symfony\Component\Console\Style\SymfonyStyle;

final class AddDeleteCurrentEnvReleaseBranchMigration implements ConfigMigration
{
    public function getFromVersion(): int
    {
        return 4;
    }

    public function getToVersion(): int
    {
        return 5;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, SymfonyStyle $io): array
    {
        foreach ($config['envs'] as &$env) {
            $env['deleteCurrentEnvReleaseBranch'] ??= false;
        }
        unset($env);

        $config['version'] = $this->getToVersion();

        return $config;
    }
}
