<?php

namespace Ezdeliver\Vcs;

use Castor\Context;
use Ezdeliver\Model\Pr;
use Ezdeliver\Vcs\Result\GlobalMergeResult;
use Symfony\Component\Console\Style\SymfonyStyle;

class GitWorkspace
{
    public function __construct(
        private readonly GitDriver $gitDriver,
        private readonly Context $context,
        private readonly MergeStrategyInterface $mergeStrategy,
        private readonly PrReleaseInfoFormatter $prReleaseInfoFormatter,
        private readonly SymfonyStyle $io,
    ) {
    }

    public function isClear(): bool
    {
        $status = $this->gitDriver->status($this->context);
        $this->verbose('git status', $status);

        return str_contains($status, 'nothing to commit, working tree clean');
    }

    public function hasChangesToBeCommited(): bool
    {
        $status = $this->gitDriver->status($this->context);
        $this->verbose('git status', $status);

        return str_contains($status, 'Changes to be committed');
    }

    public function getStatus(): string
    {
        $status = $this->gitDriver->status($this->context);
        $this->verbose('git status', $status);

        return $status;
    }

    public function updateAndCheckoutBranch(string $branchName): void
    {
        $this->verbose('git fetch --all', $this->gitDriver->fetchAll($this->context));
        $this->verbose('git checkout', $this->gitDriver->checkout($this->context, $branchName, false));
        $this->verbose('git pull --rebase', $this->gitDriver->pull($this->context, true));
    }

    public function createAndCheckoutBranch(string $branchName): void
    {
        $this->verbose('git checkout -b', $this->gitDriver->checkout($this->context, $branchName, true));
    }

    /**
     * @param array<Pr> $prsToDeliver
     */
    public function mergePrs(array $prsToDeliver): GlobalMergeResult
    {
        $this->io->progressStart(count($prsToDeliver));

        foreach ($prsToDeliver as $currentPrToDeliver) {
            if ($currentPrToDeliver->isHandled()) {
                continue;
            }

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

    /**
     * @param array<Pr> $prsDelivered
     */
    public function addGitReleaseInfo(array $prsDelivered): void
    {
        $this->io->info('write release info in current branch');

        $gitMessage = $this->prReleaseInfoFormatter->formatReleaseMessage($prsDelivered);

        $this->verbose('git commit', $this->gitDriver->commit($this->context, $gitMessage, true));
    }

    public function pushRelease(string $branchName): void
    {
        if ($this->io->isVerbose()) {
            $this->io->comment(sprintf('git push --set-upstream origin %s', $branchName));
        }

        $this->gitDriver->push($this->context, $branchName);
    }

    public function applyConflictResolution(): void
    {
        $this->mergeStrategy->applyConflictResolution($this->context);
    }

    private function verbose(string $label, string $output): void
    {
        if (!$this->io->isVerbose()) {
            return;
        }

        $this->io->comment('' === $output ? sprintf('%s (no output)', $label) : sprintf('%s: %s', $label, $output));
    }
}
