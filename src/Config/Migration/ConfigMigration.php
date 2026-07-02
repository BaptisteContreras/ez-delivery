<?php

namespace Ezdeliver\Config\Migration;

use Symfony\Component\Console\Style\SymfonyStyle;

interface ConfigMigration
{
    public function getFromVersion(): int;

    public function getToVersion(): int;

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, SymfonyStyle $io): array;
}
