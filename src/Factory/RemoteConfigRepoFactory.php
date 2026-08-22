<?php

namespace Ezdeliver\Factory;

use Castor\Context;
use Ezdeliver\RemoteConfig\GitDriver;
use Ezdeliver\RemoteConfig\RemoteConfigHandler;
use Ezdeliver\RemoteConfig\RemoteConfigRepo;
use Ezdeliver\RemoteConfig\RemoteRepoLinkStorage;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

use function Castor\context;
use function Castor\fs;
use function Castor\io;

class RemoteConfigRepoFactory
{
    private ?RemoteConfigRepo $remoteConfigRepo = null;
    private ?RemoteConfigHandler $remoteConfigHandler = null;
    private ?RemoteRepoLinkStorage $remoteRepoLinkStorage = null;
    private ?GitDriver $gitDriver = null;

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly Filesystem $fs,
        private readonly Context $context,
        private readonly string $configsDirPath,
    ) {
    }

    public static function initFromCastorGlobalContext(): self
    {
        $context = context();

        return new self(io(), fs(), $context, (string) $context->environment[CONFIG_PATH_ENV_VAR]);
    }

    public function createRemoteConfigRepo(): RemoteConfigRepo
    {
        return $this->remoteConfigRepo ??= new RemoteConfigRepo(
            $this->createRemoteRepoLinkStorage(),
            $this->createGitDriver(),
            $this->fs,
            $this->context,
            sprintf('%s/remote-repo-clone', $this->configsDirPath),
            sprintf('%s/remote-state', $this->configsDirPath),
        );
    }

    public function createRemoteConfigHandler(): RemoteConfigHandler
    {
        return $this->remoteConfigHandler ??= new RemoteConfigHandler($this->createRemoteConfigRepo(), $this->io);
    }

    private function createRemoteRepoLinkStorage(): RemoteRepoLinkStorage
    {
        return $this->remoteRepoLinkStorage ??= new RemoteRepoLinkStorage(
            $this->fs,
            (new SfFactory())->createSfSerializer(),
            sprintf('%s/config/remote-repo.json', $this->configsDirPath)
        );
    }

    private function createGitDriver(): GitDriver
    {
        return $this->gitDriver ??= new GitDriver();
    }
}
