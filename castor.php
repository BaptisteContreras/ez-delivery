<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Context;
use Ezdeliver\Factory\PackagerFactory;

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
