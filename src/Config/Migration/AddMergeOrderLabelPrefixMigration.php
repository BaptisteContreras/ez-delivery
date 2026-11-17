<?php

namespace Ezdeliver\Config\Migration;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Symfony\Component\Console\Style\SymfonyStyle;

final class AddMergeOrderLabelPrefixMigration implements ConfigMigration
{
    public function getFromVersion(): int
    {
        return 6;
    }

    public function getToVersion(): int
    {
        return 7;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, SymfonyStyle $io): array
    {
        $config['mergeOrderLabelPrefix'] ??= ProjectConfiguration::DEFAULT_MERGE_ORDER_LABEL_PREFIX;
        $config['enableSortMr'] ??= false;
        $config['version'] = $this->getToVersion();

        return $config;
    }
}
