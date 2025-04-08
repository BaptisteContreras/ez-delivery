<?php

namespace Ezdeliver\Factory;

use Ezdeliver\Config\Handler as ConfigHandler;
use Ezdeliver\Config\InteractiveBuilder;
use Ezdeliver\Config\StorageHandler;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

class ConfigHandlerFactory
{
    private ?ConfigHandler $configHandler = null;
    private ?StorageHandler $storageHandler = null;
    private ?InteractiveBuilder $interactiveBuilder = null;

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly Filesystem $fs,
        private readonly SerializerInterface $serializer,
        private readonly string $configsDirPath,
    ) {
    }

    public function createHandler(): ConfigHandler
    {
        return $this->configHandler ??= new ConfigHandler(
            $this->createStorageHandler(),
            $this->createInteractiveBuilder(),
        );
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
