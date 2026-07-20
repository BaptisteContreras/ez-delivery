<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;
use Ezdeliver\Factory\ConfigHandlerFactory;
use Ezdeliver\Factory\PackagerFactory;

use function Castor\io;

const DEFAULT_CONFIG_PATH = '~/.ez-delivery';
const CONFIG_PATH_ENV_VAR = 'EZ_DELIVERY_CONFIG_PATH';

#[AsContext(name: 'init', default: true)]
function defaultContext(): Context
{
    return (new Context())->withEnvironment([CONFIG_PATH_ENV_VAR => $_ENV[CONFIG_PATH_ENV_VAR] ?? DEFAULT_CONFIG_PATH]);
}

#[AsTask(description: 'init project config')]
function initProjectConfig(): void
{
    PackagerFactory::initFromCastorGlobalContext()
        ->createPackager()
        ->initProjectConfig();
}

#[AsTask(description: 'Create or resume a package')]
function package(string $project): void
{
    exit(PackagerFactory::initFromCastorGlobalContext()
        ->createPackager()
        ->createPackage($project));
}

#[AsTask(description: 'Upgrade a project config to the latest version')]
function migrateConfig(string $project): void
{
    exit(ConfigHandlerFactory::initFromCastorGlobalContext()
        ->createMigrator()
        ->migrateProjectConfig($project));
}

#[AsTask(description: 'Create or update a token in the vault')]
function setToken(string $name): void
{
    $io = io();
    $token = $io->askHidden('Token value');

    ConfigHandlerFactory::initFromCastorGlobalContext()->createHandler()->setToken($name, $token);

    $io->success(sprintf('Token "%s" saved.', $name));
}
