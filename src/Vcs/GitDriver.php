<?php

namespace Ezdeliver\Vcs;

use Castor\Context;
use Ezdeliver\Vcs\Result\CherryPickResult;
use function Castor\capture;
use function Castor\run;

class GitDriver
{
    public function status(Context $context): string
    {
        return capture('git status', context: $context);
    }

    public function fetchAll(Context $context): self
    {
        capture('git fetch --all', context: $context);

        return $this;
    }

    public function checkout(Context $context, string $branchName, bool $create): self
    {
        capture(sprintf('git checkout %s %s', $create ? '-b' : '', $branchName), context: $context);

        return $this;
    }

    public function pull(Context $context, bool $rebase): self
    {
        capture(sprintf('git pull %s', $rebase ? '--rebase' : ''), context: $context);

        return $this;
    }

    public function cherryPick(Context $context, string $commitSha): CherryPickResult
    {
        return CherryPickResult::buildFromProcess(
            run(sprintf('git cherry-pick --allow-empty %s', $commitSha), context: $context->withQuiet()->withAllowFailure())
        );
    }

    public function skipCkerryPick(Context $context): void
    {
        capture('git cherry-pick --skip', context: $context);
    }

    public function push(Context $context, string $branchName): void
    {
        run(sprintf('git push --set-upstream origin %s', $branchName), context: $context);
    }
}