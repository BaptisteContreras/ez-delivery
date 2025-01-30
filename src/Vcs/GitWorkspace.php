<?php

namespace Ezdeliver\Vcs;

use Castor\Context;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Vcs\Result\GlobalMergeResult;
use Ezdeliver\Vcs\Result\MergeResult;
use Symfony\Component\Console\Style\SymfonyStyle;

class GitWorkspace
{

    public function __construct(
        private readonly GitDriver $gitDriver,
        private readonly Context $context,
        private readonly MergeStrategyInterface $mergeStrategy,
        private readonly SymfonyStyle $io
    )
    {
    }
    public function isClear(): bool
    {
        return str_contains($this->gitDriver->status($this->context), 'nothing to commit, working tree clean');
    }

    public function updateAndCheckoutBranch(string $branchName): void
    {
        $this->gitDriver
            ->fetchAll($this->context)
            ->checkout($this->context, $branchName, false)
            ->pull($this->context, true);
    }

    public function createAndCheckoutBranch(string $branchName): void
    {
        $this->gitDriver->checkout($this->context, $branchName,true);
    }

    /**
     * @param array<Pr> $prsToDeliver
     */
    public function mergePrs(array $prsToDeliver): GlobalMergeResult
    {
        $this->io->progressStart(count($prsToDeliver));

        foreach ($prsToDeliver as $currentPrToDeliver) {
            if ($currentPrToDeliver->isHandled()) continue;

            $this->io->info(sprintf('handling PR #%s : %s', $currentPrToDeliver->getId(), $currentPrToDeliver->getTitle()));

            $mergeResult = $this->mergeStrategy->mergePr($this->context, $currentPrToDeliver);

            if ($mergeResult->isConflicting()) {
                return GlobalMergeResult::conflict($mergeResult, $currentPrToDeliver);
            }

            if ($mergeResult->isOnError()) {
                return GlobalMergeResult::error($currentPrToDeliver);
            }

            $currentPrToDeliver->markHandled();

            $this->io->success(sprintf('PR #%s : %s is merged', $currentPrToDeliver->getId(), $currentPrToDeliver->getTitle()));
            $this->io->progressAdvance();
        }


        $this->io->progressFinish();

        return GlobalMergeResult::success();
    }
}