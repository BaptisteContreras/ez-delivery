<?php

namespace Ezdeliver\Factory;

use Castor\Context;
use Ezdeliver\InteractionHandler;
use Ezdeliver\Packager;
use Ezdeliver\StorageHandler as PackageStorageHandler;
use Ezdeliver\Vcs\GitDriver;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

use function Castor\context;
use function Castor\fs;
use function Castor\io;

class PackagerFactory
{
    private readonly ConfigHandlerFactory $configHandlerFactory;
    private readonly SfFactory $sfFactory;
    private readonly RemoteRepoFactory $remoteRepoFactory;
    private readonly GitWorkspaceFactory $gitWorkspaceFactory;

    private ?PackageStorageHandler $packageStorageHandler = null;
    private ?Packager $packager = null;

    private ?InteractionHandler $interactionHandler = null;

    private ?GitDriver $gitDriver = null;

    private function __construct(
        private readonly Context $context,
        private readonly SymfonyStyle $io,
        private readonly Filesystem $fs,
    ) {
        $this->sfFactory = new SfFactory();

        $this->configHandlerFactory = new ConfigHandlerFactory(
            $this->io,
            $this->fs,
            $this->sfFactory->createSfSerializer(),
            $this->getConfigsDirPathFromContext()
        );

        $this->remoteRepoFactory = new RemoteRepoFactory($this->io);
        $this->gitWorkspaceFactory = new GitWorkspaceFactory($this->createGitDriver(), $this->io);
    }

    public static function initFromCastorGlobalContext(): self
    {
        return new self(
            context(),
            io(),
            fs()
        );
    }

    public function createPackager(): Packager
    {
        return $this->packager ??= new Packager(
            $this->context,
            $this->configHandlerFactory->createHandler(),
            $this->createInteractionHandler(),
            $this->createPackageStorageHandler(),
            $this->io,
            $this->remoteRepoFactory->createRemoteRepo(),
            $this->gitWorkspaceFactory,
            $this->getConfigsDirPathFromContext()
        );
    }

    private function getConfigsDirPathFromContext(): string
    {
        return $this->context->environment[CONFIG_PATH_ENV_VAR];
    }

    private function createPackageStorageHandler(): PackageStorageHandler
    {
        return $this->packageStorageHandler ??= new PackageStorageHandler(
            $this->io,
            $this->fs,
            $this->sfFactory->createSfSerializer(),
            $this->getConfigsDirPathFromContext()
        );
    }

    private function createInteractionHandler(): InteractionHandler
    {
        return $this->interactionHandler ??= new InteractionHandler($this->io);
    }

    private function createGitDriver(): GitDriver
    {
        return $this->gitDriver ??= new GitDriver();
    }
}
