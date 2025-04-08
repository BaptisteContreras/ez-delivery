<?php

namespace Ezdeliver;

use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\Model\ProjectEnvConfig;
use Symfony\Component\Console\Style\SymfonyStyle;

class InteractionHandler
{
    private const string YES = 'yes';
    private const string NO = 'no';

    public function __construct(
        private readonly SymfonyStyle $io,
    ) {
    }

    public function askToSelectEnv(ProjectConfiguration $projectConfiguration): ProjectEnvConfig
    {
        $envs = array_map(fn (ProjectEnvConfig $projectEnvConfig) => $projectEnvConfig->getName(), $projectConfiguration->getEnvs());

        return $projectConfiguration->getEnv($this->io->choice('Env to use', $envs, current($envs)));
    }

    public function askToProceedRelease(ProjectEnvConfig $selectedEnv): bool
    {
        return self::YES === $this->io
            ->choice(
                sprintf('Ready to deliver these PRs for env %s ?', $selectedEnv->getName()), [self::YES, self::NO],
                self::YES
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

    public function askToPushReleaseBranch(string $branchName): bool
    {
        return self::YES === $this->io->choice(sprintf('push new branch %s ?', $branchName), [self::YES, self::NO], self::YES);
    }

    public function askToCommitChanges(): bool
    {
        return self::YES === $this->io->choice('Commit theses changes ?', [self::YES, self::NO], self::YES);
    }

    public function askToResumeLastRelease(): bool
    {
        return self::YES === $this->io->choice('Resume paused delivery ?', [self::YES, self::NO], self::YES);
    }
}
