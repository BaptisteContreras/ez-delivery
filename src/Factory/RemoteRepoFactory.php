<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Repo\GithubDriver;
use Ezdeliver\Repo\GitlabDriver;
use Ezdeliver\Repo\RemoteRepo;
use Ezdeliver\Repo\RemoteRepoDriver;
use Symfony\Component\Console\Style\SymfonyStyle;

class RemoteRepoFactory
{
    private ?RemoteRepo $remoteRepo = null;

    /**
     * @var array<RemoteRepoDriver>|null
     */
    private ?array $remoteRepoDrivers = null;

    public function __construct(
        private readonly SymfonyStyle $io
    )
    {
    }


    public function createRemoteRepo(): RemoteRepo
    {
        return $this->remoteRepo ??= new RemoteRepo($this->createRemoteRepoDrivers());
    }

    /**
     * @return array<RemoteRepoDriver>
     */
    private function createRemoteRepoDrivers(): array
    {
        return $this->remoteRepoDrivers ??= [new GithubDriver($this->io), new GitlabDriver($this->io)];
    }
}