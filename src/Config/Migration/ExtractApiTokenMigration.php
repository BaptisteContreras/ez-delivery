<?php

namespace Ezdeliver\Config\Migration;

use Ezdeliver\Token\TokenVault;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ExtractApiTokenMigration implements ConfigMigration
{
    public function __construct(
        private readonly TokenVault $tokenVault,
    ) {
    }

    public function getFromVersion(): int
    {
        return 1;
    }

    public function getToVersion(): int
    {
        return 2;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function migrate(array $config, SymfonyStyle $io): array
    {
        $rawToken = $config['repo']['apiToken'];

        $ref = $this->tokenVault->findRefByValue($rawToken) ?? $this->promptForRef($rawToken, $config, $io);

        unset($config['repo']['apiToken']);
        $config['repo']['apiTokenRef'] = $ref;
        $config['version'] = $this->getToVersion();

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function promptForRef(string $rawToken, array $config, SymfonyStyle $io): string
    {
        $suggestedRef = sprintf('%s-%s', $config['repo']['type'], $config['repo']['namespace'] ?? $config['repo']['owner']);

        $ref = $io->ask('This token needs a reference name', $suggestedRef);

        if ($this->tokenVault->has($ref)) {
            $io->warning(sprintf('A token named "%s" already exists and will be overwritten.', $ref));
        }

        $this->tokenVault->set($ref, $rawToken);

        return $ref;
    }
}
