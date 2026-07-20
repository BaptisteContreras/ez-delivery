<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Repo\GithubDriver;
use Ezdeliver\Repo\GitlabLabelResolver;
use Ezdeliver\Repo\GitlabLinkedIssueDriver;
use Ezdeliver\Repo\GitlabMrLabelDriver;
use Ezdeliver\Repo\RemoteRepo;
use Ezdeliver\Repo\RemoteRepoDriver;
use Ezdeliver\Token\TokenVault;
use Symfony\Component\Console\Style\SymfonyStyle;

class RemoteRepoFactory
{
    private ?RemoteRepo $remoteRepo = null;

    /**
     * @var array<RemoteRepoDriver>|null
     */
    private ?array $remoteRepoDrivers = null;

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly TokenVault $tokenVault,
    ) {
    }

    public function createRemoteRepo(): RemoteRepo
    {
        return $this->remoteRepo ??= new RemoteRepo($this->createRemoteRepoDrivers(), $this->io);
    }

    /**
     * @return array<RemoteRepoDriver>
     */
    private function createRemoteRepoDrivers(): array
    {
        $labelResolver = new GitlabLabelResolver($this->io, $this->tokenVault);

        return $this->remoteRepoDrivers ??= [
            new GithubDriver($this->io, $this->tokenVault),
            new GitlabLinkedIssueDriver($this->io, $labelResolver, $this->tokenVault),
            new GitlabMrLabelDriver($this->io, $labelResolver, $this->tokenVault),
        ];
    }
}
