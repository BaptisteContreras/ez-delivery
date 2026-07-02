<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Config\Handler as ConfigHandler;
use Ezdeliver\Config\InteractiveBuilder;
use Ezdeliver\Config\Migration\MigrationRunner;
use Ezdeliver\Config\Migrator;
use Ezdeliver\Config\StorageHandler;
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
            $context->environment[CONFIG_PATH_ENV_VAR]
        );
    }

    public function createHandler(): ConfigHandler
    {
        return $this->configHandler ??= new ConfigHandler(
            $this->createStorageHandler(),
            $this->createInteractiveBuilder(),
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

    private function createMigrationRunner(): MigrationRunner
    {
        return $this->migrationRunner ??= new MigrationRunner([
            // no migrations registered yet — Ezdeliver\Config\Model\ProjectConfiguration::CURRENT_VERSION is still 1
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
        return $this->interactiveBuilder ?? $this->interactiveBuilder = new InteractiveBuilder($this->io, $this->createStorageHandler());
    }
}
