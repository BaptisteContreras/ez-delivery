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

        return $projectConfiguration->getEnv($this->io->choice('Env to use', $envs, current($envs)));
    }

    public function askToProceedRelease(ProjectEnvConfig $selectedEnv): bool
    {
        return YES === $this->io
            ->choice(
                sprintf('Ready to deliver these PRs for env %s ?', $selectedEnv->getName()), [YES, NO],
                YES
            );
    }

    public function askDeliveryBranchName(ProjectEnvConfig $selectedEnv): string
    {
        return $this->io->ask(
            'Enter delivery branch name',
            sprintf('%s_%s', $selectedEnv->getName(), (new \DateTimeImmutable())->format('Y-m-d'))
        );
    }

    public function askBaseBranch(ProjectConfiguration $projectConfiguration): string
    {
        return $this->io->ask('Enter base branch name', $projectConfiguration->getBaseBranch());
    }

}