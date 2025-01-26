<?php

namespace Ezdeliver;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\Model\ProjectEnvConfig;
use Symfony\Component\Console\Style\SymfonyStyle;

class InteractionHandler
{

    public function __construct(
        private readonly SymfonyStyle $io
    ) {
    }

    public function askToSelectEnv(ProjectConfiguration $projectConfiguration): ProjectEnvConfig
    {
        $envs = array_map(fn(ProjectEnvConfig $projectEnvConfig) => $projectEnvConfig->getName(), $projectConfiguration->getEnvs());

        $envSelected = $projectConfiguration->getEnv($this->io->choice('Env to use', $envs, current($envs)));

        return $envSelected;
    }
}