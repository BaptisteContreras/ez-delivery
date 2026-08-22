<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Core\Repo\GithubDriver;
use Ezdeliver\Core\Repo\GitlabLabelResolver;
use Ezdeliver\Core\Repo\GitlabLinkedIssueDriver;
use Ezdeliver\Core\Repo\GitlabMrLabelDriver;
use Ezdeliver\Core\Repo\RemoteRepo;
use Ezdeliver\Core\Repo\RemoteRepoDriver;
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
