<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;
use Ezdeliver\Factory\ConfigHandlerFactory;
use Ezdeliver\Factory\PackagerFactory;
use Ezdeliver\Factory\RemoteConfigRepoFactory;

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
function package(string $project, bool $remote = false): void
{
    exit(PackagerFactory::initFromCastorGlobalContext()
        ->createPackager($remote)
        ->createPackage($project, $remote));
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

#[AsTask(name: 'remote-config', description: 'Link, unlink, sync, or show info about the remote config repo')]
function remoteConfig(
    bool $link = false,
    bool $unlink = false,
    bool $info = false,
    bool $sync = false,
): void {
    $io = io();
    $flagCount = (int) $link + (int) $unlink + (int) $info + (int) $sync;

    if (1 !== $flagCount) {
        $io->error('Pass exactly one of --link, --unlink, --info, --sync.');
        exit(1);
    }

    $remoteConfigHandler = RemoteConfigRepoFactory::initFromCastorGlobalContext()->createRemoteConfigHandler();

    exit(match (true) {
        $link => $remoteConfigHandler->link(),
        $unlink => $remoteConfigHandler->unlink(),
        $info => $remoteConfigHandler->info(),
        $sync => $remoteConfigHandler->sync(),
        default => throw new \LogicException('Unreachable — flag count was validated above.'),
    });
}
