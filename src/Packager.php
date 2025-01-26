<?php

namespace Ezdeliver;

use Castor\Context;
use Ezdeliver\Config\Handler as ConfigHandler;
use Ezdeliver\Repo\RemoteRepo;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\GitWorkspace;
use Symfony\Component\Console\Style\SymfonyStyle;

class Packager
{
    public function __construct(
        private Context $context,
        private readonly ConfigHandler $configHandler,
        private readonly InteractionHandler $interactionHandler,
        private readonly StorageHandler $storageHandler,
        private readonly SymfonyStyle $io,
        private readonly GitDriver $gitDriver,
        private readonly RemoteRepo $remoteRepo,
        private readonly string $configsDirPath,
    )
    {
    }

    public function initProjectConfig(): void
    {
        $projectConfig = $this->configHandler->createProjectConfig();
        $this->storageHandler->createProjectTmpStorageDir($projectConfig);
    }

    public function createPackage(string $project): void
    {
        $projectConfig = $this->configHandler->loadProjectConfig($project);
        $selectedEnv = $this->interactionHandler->askToSelectEnv($projectConfig);

        $currentContext = $this->context->withWorkingDirectory($projectConfig->getSrc());

        $gitWorkspace = new GitWorkspace($this->gitDriver, $currentContext);


        if ($this->storageHandler->hasPausedDelivery($projectConfig)) {
            $this->io->warning('Paused delivery found');
        }

        if (!$gitWorkspace->isClear()) {
            $this->io->error('current git state is not clean');

            exit(1);
        }

        $this->remoteRepo->getPrsToDeliver($projectConfig->getRepo(), $selectedEnv);
        dd($projectConfig);
    }

}