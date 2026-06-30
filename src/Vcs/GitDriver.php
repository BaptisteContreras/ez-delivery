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

    public function fetchAll(Context $context): string
    {
        return capture('git fetch --all', context: $context);
    }

    public function checkout(Context $context, string $branchName, bool $create): string
    {
        return capture(sprintf('git checkout %s %s', $create ? '-b' : '', $branchName), context: $context);
    }

    public function pull(Context $context, bool $rebase): string
    {
        return capture(sprintf('git pull %s', $rebase ? '--rebase' : ''), context: $context);
    }

    public function cherryPick(Context $context, string $commitSha): CherryPickResult
    {
        return CherryPickResult::buildFromProcess(
            run(sprintf('git cherry-pick --allow-empty %s', $commitSha), context: $context->withQuiet()->withAllowFailure())
        );
    }

    public function skipCkerryPick(Context $context): string
    {
        return capture('git cherry-pick --skip', context: $context);
    }

    public function push(Context $context, string $branchName): void
    {
        run(sprintf('git push --set-upstream origin %s', $branchName), context: $context);
    }

    public function continueCkerryPick(Context $context): string
    {
        return capture('git cherry-pick --continue', context: $context);
    }

    public function commit(Context $context, string $message, bool $allowEmpty): string
    {
        return capture($this->buildCommitCommand($message, $allowEmpty), context: $context);
    }

    /**
     * Built as an argument array (not a shell string) so the message is passed to git
     * as a single argument, regardless of characters like `"` it may contain.
     *
     * @return array<string>
     */
    private function buildCommitCommand(string $message, bool $allowEmpty): array
    {
        $command = ['git', 'commit', '-m', $message];

        if ($allowEmpty) {
            $command[] = '--allow-empty';
        }

        return $command;
    }
}
