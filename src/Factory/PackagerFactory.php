<?php

namespace Ezdeliver\Factory;

use Castor\Context;
use Ezdeliver\Config\Handler as ConfigHandler;
use Ezdeliver\Config\StorageHandler as ConfigStorageHandler;
use Ezdeliver\Core\InteractionHandler;
use Ezdeliver\Core\Packager;
use Ezdeliver\Core\StorageHandler as PackageStorageHandler;
use Ezdeliver\Core\Vcs\GitDriver;
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
    private readonly RemoteConfigRepoFactory $remoteConfigRepoFactory;
    private readonly GitWorkspaceFactory $gitWorkspaceFactory;
    private readonly PrDisplayStrategyFactory $prDisplayStrategyFactory;

    private ?Packager $packager = null;

    private ?InteractionHandler $interactionHandler = null;

    private ?GitDriver $gitDriver = null;

    private ?PackageStorageHandler $packageStorageHandler = null;

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

        $this->remoteRepoFactory = new RemoteRepoFactory($this->io, $this->configHandlerFactory->createTokenVault());
        $this->remoteConfigRepoFactory = new RemoteConfigRepoFactory($this->io, $this->fs, $this->context, $this->getConfigsDirPathFromContext());
        $this->gitWorkspaceFactory = new GitWorkspaceFactory($this->createGitDriver(), $this->io, new PrReleaseInfoFormatterFactory());
        $this->prDisplayStrategyFactory = new PrDisplayStrategyFactory($this->io);
    }

    public static function initFromCastorGlobalContext(): self
    {
        return new self(
            context(),
            io(),
            fs()
        );
    }

    public function createPackager(bool $remote = false): Packager
    {
        return $this->packager ??= new Packager(
            $this->context,
            $this->createConfigHandler($remote),
            $this->createInteractionHandler(),
            $this->createPackageStorageHandler($remote),
            $this->io,
            $this->remoteRepoFactory->createRemoteRepo(),
            $this->gitWorkspaceFactory,
            $this->prDisplayStrategyFactory,
            $this->getConfigsDirPathFromContext(),
            $this->configHandlerFactory->createBranchNamePatternResolver(),
        );
    }

    private function createConfigHandler(bool $remote): ConfigHandler
    {
        if (!$remote) {
            return $this->configHandlerFactory->createHandler();
        }

        $remoteConfigsPath = $this->remoteConfigRepoFactory->createRemoteConfigRepo()->getConfigsPath();
        $remoteStorageHandler = new ConfigStorageHandler($this->io, $this->fs, $this->sfFactory->createSfSerializer(), $remoteConfigsPath);

        return new ConfigHandler(
            $remoteStorageHandler,
            $this->configHandlerFactory->createInteractiveBuilder(),
            $this->configHandlerFactory->createTokenVault(),
        );
    }

    private function getConfigsDirPathFromContext(): string
    {
        return (string) $this->context->environment[CONFIG_PATH_ENV_VAR];
    }

    private function createPackageStorageHandler(bool $remote): PackageStorageHandler
    {
        $root = $remote
            ? $this->remoteConfigRepoFactory->createRemoteConfigRepo()->getRemoteStatePath()
            : sprintf('%s/local', $this->getConfigsDirPathFromContext());

        return $this->packageStorageHandler ??= new PackageStorageHandler(
            $this->io,
            $this->fs,
            $this->sfFactory->createSfSerializer(),
            $root
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
