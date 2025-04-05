<?php

namespace Ezdeliver\Vcs;

use Castor\Context;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Vcs\Result\MergeResult;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Castor\run;

class CherryPickMergeStrategy implements MergeStrategyInterface
{


    public function __construct(
        private readonly GitDriver $gitDriver,
        private readonly SymfonyStyle $io
    )
    {
    }

    public function mergePr(Context $context, Pr $pr): MergeResult
    {
        foreach ($pr->getCommits() as $commit) {
            if ($commit->isHandled()) continue;

            $this->io->info(sprintf('handling commit %s : "%s"', $commit->getSha(), $commit->getMessage()));

            $commit->markHandled();
            $cherryPickResult = $this->gitDriver->cherryPick($context, $commit->getSha());

            if ($cherryPickResult->isSuccess()) {
                $this->io->success(sprintf('commit %s OK', $commit->getSha()));

                continue;
            }

            if (str_contains($cherryPickResult->getErrorOutput(), '--allow-empty')) {
                $this->io->warning(sprintf('commit %s is already on the delivery branch', $commit->getSha()));

                $this->gitDriver->skipCkerryPick($context);

                continue;
            }

            if (str_contains($cherryPickResult->getOutput(), 'CONFLICT')) {
                $this->io->warning(sprintf('commit %s is in conflict', $commit->getSha()));
                $commit->markConflicted();

                return MergeResult::conflict($commit);
            }

            $this->io->error(sprintf('commit %s FAILED', $commit->getSha()));

            $this->io->warning($cherryPickResult->getOutput());
            $this->io->warning($cherryPickResult->getErrorOutput());

            return MergeResult::error();
        }

        return MergeResult::success();
    }

    public function applyConflictResolution(Context $context): void
    {
        $this->gitDriver->continueCkerryPick($context);
    }
}
