<?php

namespace Ezdeliver;

use Castor\Context;
use Ezdeliver\Config\Handler as ConfigHandler;
use Ezdeliver\Config\Model\ProjectConfiguration;
use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Factory\GitWorkspaceFactory;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Release;
use Ezdeliver\Repo\RemoteRepo;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\GitWorkspace;
use Symfony\Component\Console\Style\SymfonyStyle;

class Packager
{
    private const int RETURN_CODE_OK = 0;
    private const int RETURN_CODE_ERROR = 1;
    private const int RETURN_CODE_CONFLICT = 2;

    public function __construct(
        private Context $context,
        private readonly ConfigHandler $configHandler,
        private readonly InteractionHandler $interactionHandler,
        private readonly StorageHandler $storageHandler,
        private readonly SymfonyStyle $io,
        private readonly RemoteRepo $remoteRepo,
        private readonly GitWorkspaceFactory $gitWorkspaceFactory,
        private readonly string $configsDirPath,
    )
    {
    }

    public function initProjectConfig(): void
    {
        $projectConfig = $this->configHandler->createProjectConfig();
        $this->storageHandler->createProjectTmpStorageDir($projectConfig);
    }

    public function createPackage(string $project): int
    {
        $projectConfig = $this->configHandler->loadProjectConfig($project);
        $selectedEnv = $this->interactionHandler->askToSelectEnv($projectConfig);

        $currentContext = $this->context->withWorkingDirectory($projectConfig->getSrc());

        $gitWorkspace = $this->gitWorkspaceFactory->createWorkspaceWithCherryPickMergeStrategy($currentContext);


        if ($this->storageHandler->hasPausedDelivery($projectConfig)) {
            $this->io->warning('Paused delivery found');

            $lastRelease = $this->storageHandler->loadRelease($projectConfig);

            $sameEnvForOldRelease = $lastRelease->getEnv() === $selectedEnv->getName();

            if ($sameEnvForOldRelease && $this->interactionHandler->askToResumeLastRelease()) {
                return $this->resume(
                    $lastRelease,
                    $currentContext,
                    $projectConfig,
                    $selectedEnv
                );
            }

            if (!$sameEnvForOldRelease) {
                $this->io->warning('Old delivery for another env, delete it...');
            }

            $this->storageHandler->purgeLastRelease($projectConfig);
            dd($lastRelease);
        }

        if (!$gitWorkspace->isClear()) {
            $this->io->error('current git state is not clean');

            return self::RETURN_CODE_ERROR;
        }

        $prsToDeliver = $this->remoteRepo->getPrsToDeliver($projectConfig->getRepo(), $selectedEnv);


        if (empty($prsToDeliver)) {
            $this->io->warning(sprintf('No PR found for env %s', $selectedEnv->getName()));

            return self::RETURN_CODE_OK;
        }


        $this->io->title('About to deliver theses PRs');
        $this->displayPrsToDeliver($prsToDeliver);

        if (!$this->interactionHandler->askToProceedRelease($selectedEnv)) {
            $this->io->error('Delivery aborted by user');

            return self::RETURN_CODE_ERROR;
        }

        $deliveryBranchName = $this->interactionHandler->askDeliveryBranchName($selectedEnv);
        $baseBranchName = $this->interactionHandler->askBaseBranch($projectConfig);

        $this->io->info(sprintf('updating %s', $baseBranchName));
        $gitWorkspace->updateAndCheckoutBranch($baseBranchName);
        $this->io->success(sprintf('%s is up to date', $baseBranchName));

        $this->io->info(sprintf('creating delivery branch %s from %s', $deliveryBranchName, $baseBranchName));
        $gitWorkspace->createAndCheckoutBranch($deliveryBranchName);
        $this->io->success(sprintf('%s is created', $deliveryBranchName));

        return $this->doMerge(
            $prsToDeliver,
            $currentContext,
            $projectConfig,
            $selectedEnv,
            $gitWorkspace,
            $deliveryBranchName
        );
    }

    /**
     * @param array<Pr> $prsToDeliver
     */
    private function displayPrsToDeliver(array $prsToDeliver): void
    {
        $this->io->table(
            ['PR #ID', 'PR title', 'issue #ID', 'issue title', 'Number of commit'],
            array_map(fn(Pr $pr) => [
                $pr->getId(),
                $pr->getTitle(),
                $pr->getClosingIssueId(),
                $pr->getClosingIssueTitle(),
                $pr->getCommitsCount()],
                $prsToDeliver
            )
        );

    }

    private function resume(
        Release $release,
        Context $context,
        ProjectConfiguration $projectConfiguration,
        ProjectEnvConfig $selectedEnv
    ): int
    {


    }

    /**
     * @param array<Pr> $prsToDeliver
     */
    private function doMerge(
        array $prsToDeliver,
        Context $currentContext,
        ProjectConfiguration $projectConfiguration,
        ProjectEnvConfig $selectedEnv,
        GitWorkspace $gitWorkspace,
        string $deliveryBranchName
    ): int
    {
      $prsMergeResult = $gitWorkspace->mergePrs($prsToDeliver);

      if ($prsMergeResult->isSuccess()) {

          return $this->handleMergeSuccess($gitWorkspace, $prsToDeliver, $deliveryBranchName);
      }

      if ($prsMergeResult->isOnError()) {
          $this->io->error(sprintf(
              'Stop due to git error on pr #%s : %s',
              $prsMergeResult->getProblematicPr()->getId(),
              $prsMergeResult->getProblematicPr()->getTitle()
          ));

          return self::RETURN_CODE_ERROR;
      }

      if ($prsMergeResult->isConflicting()) {
          return $this->handleMergeConflict(
              $prsToDeliver,
              $prsMergeResult->getProblematicPr(),
              $prsMergeResult->getConflictingCommit(),
              $projectConfiguration,
              $selectedEnv,
              $deliveryBranchName
          );
      }


      throw new \Exception('Unknown merge result state');
    }

    /**
     * @param array<Pr> $prsDelivered
     */
    private function handleMergeSuccess(
        GitWorkspace $gitWorkspace,
        array $prsDelivered,
        string $deliveryBranchName
    ): int
    {

        $gitWorkspace->addGitReleaseInfo($prsDelivered);

        if ($this->interactionHandler->askToPushReleaseBranch($deliveryBranchName)) {
            $gitWorkspace->pushRelease($deliveryBranchName);

            $this->io->success('branch pushed');
        }

        return self::RETURN_CODE_OK;
    }

    private function handleMergeConflict(
        array $prsToDeliver,
        Pr $problematicPr,
        Commit $problematicCommit,
        ProjectConfiguration $projectConfiguration,
        ProjectEnvConfig $selectedEnv,
        string $deliveryBranchName
    ): int
    {
        $release = new Release(
            $prsToDeliver,
            $problematicPr->getId(),
            $problematicCommit->getSha(),
            $selectedEnv->getName(),
            $deliveryBranchName
        );

        $this->io->info(sprintf(
            'current delivery state stored in %s',
            $this->storageHandler->storeRelease($release, $projectConfiguration)
        ));


        $this->io->warning('delivery pause here. Resolve conflict and restart command to resume delivery');

        return self::RETURN_CODE_CONFLICT;
    }


}