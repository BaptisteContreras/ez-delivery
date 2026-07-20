<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Config\Handler as ConfigHandler;
use Ezdeliver\Config\InteractiveBuilder;
use Ezdeliver\Config\Migration\ExtractApiTokenMigration;
use Ezdeliver\Config\Migration\MigrationRunner;
use Ezdeliver\Config\Migrator;
use Ezdeliver\Config\StorageHandler;
use Ezdeliver\Token\TokenVault;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

use function Castor\context;
use function Castor\fs;
use function Castor\io;

class ConfigHandlerFactory
{
    private ?ConfigHandler $configHandler = null;
    private ?StorageHandler $storageHandler = null;
    private ?InteractiveBuilder $interactiveBuilder = null;
    private ?Migrator $migrator = null;
    private ?MigrationRunner $migrationRunner = null;
    private ?TokenVault $tokenVault = null;

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly Filesystem $fs,
        private readonly SerializerInterface $serializer,
        private readonly string $configsDirPath,
    ) {
    }

    public static function initFromCastorGlobalContext(): self
    {
        $context = context();

        return new self(
            io(),
            fs(),
            (new SfFactory())->createSfSerializer(),
            (string) $context->environment[CONFIG_PATH_ENV_VAR]
        );
    }

    public function createHandler(): ConfigHandler
    {
        return $this->configHandler ??= new ConfigHandler(
            $this->createStorageHandler(),
            $this->createInteractiveBuilder(),
            $this->createTokenVault(),
        );
    }

    public function createMigrator(): Migrator
    {
        return $this->migrator ??= new Migrator(
            $this->createStorageHandler(),
            $this->createMigrationRunner(),
            $this->serializer,
            $this->io,
        );
    }

    public function createTokenVault(): TokenVault
    {
        return $this->tokenVault ??= new TokenVault($this->fs, sprintf('%s/tokens.json', $this->configsDirPath));
    }

    private function createMigrationRunner(): MigrationRunner
    {
        return $this->migrationRunner ??= new MigrationRunner([
            new ExtractApiTokenMigration($this->createTokenVault()),
        ]);
    }

    private function createStorageHandler(): StorageHandler
    {
        return $this->storageHandler ??= new StorageHandler(
            $this->io,
            $this->fs,
            $this->serializer,
            $this->configsDirPath
        );
    }

    private function createInteractiveBuilder(): InteractiveBuilder
    {
        return $this->interactiveBuilder ?? $this->interactiveBuilder = new InteractiveBuilder($this->io, $this->createStorageHandler(), $this->createTokenVault());
    }
}
