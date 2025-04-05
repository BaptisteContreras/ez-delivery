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

    public function hasChangesToBeCommited(): bool
    {
        return str_contains($this->gitDriver->status($this->context), 'Changes to be committed');
    }

    public function getStatus(): string
    {
        return $this->gitDriver->status($this->context);
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

    /**
     * @param array<Pr> $prsDelivered
     */
    public function addGitReleaseInfo(array $prsDelivered): void
    {
        $this->io->info('write release info in current branch');

        $gitMessage = sprintf('AUTO RELEASE %s', PHP_EOL);
        $gitMessage .= sprintf('Number of PRs delivered : %s %s',count($prsDelivered), PHP_EOL);
        $gitMessage .= sprintf('PR #ID, Issue #ID, Issue title, Number of commit, [Commits] %s%s%s', PHP_EOL,PHP_EOL,PHP_EOL);

        foreach ($prsDelivered as $pr) {
            $gitMessage = $this->addPrInfo($pr, $gitMessage);
        }

    }

    private function addPrInfo(Pr $pr, string $gitMessage): string
    {
        $commits = implode(';', array_map(fn(Commit $commit) => sprintf('\\"%s\\"', $commit->getSha()), $pr->getCommits()));

        return sprintf(
            '%s-   #%s, #%s, \"%s\", %s, [%s] %s',
            $gitMessage,
            $pr->getId(),
            $pr->getClosingIssueId(),
            $pr->getClosingIssueTitle(),
            $pr->getCommitsCount(),
            $commits,
            PHP_EOL
        );
    }
    public function pushRelease(string $branchName): void
    {
        $this->gitDriver->push($this->context, $branchName);
    }

    public function applyConflictResolution(): void
    {
        $this->mergeStrategy->applyConflictResolution($this->context);
    }
}
