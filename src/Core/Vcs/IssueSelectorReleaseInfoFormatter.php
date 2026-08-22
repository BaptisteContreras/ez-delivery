<?php

namespace Ezdeliver\Core\Vcs;

use Ezdeliver\Core\Model\Commit;
use Ezdeliver\Core\Model\Pr;

final class IssueSelectorReleaseInfoFormatter implements PrReleaseInfoFormatter
{
    /**
     * @param array<Pr> $prsDelivered
     */
    public function formatReleaseMessage(array $prsDelivered): string
    {
        $message = sprintf('AUTO RELEASE %s', PHP_EOL);
        $message .= sprintf('Number of PRs delivered : %s %s', count($prsDelivered), PHP_EOL);
        $message .= sprintf('PR #ID, Issue #ID, Issue title, Number of commit, [Commits] %s%s%s', PHP_EOL, PHP_EOL, PHP_EOL);

        foreach ($prsDelivered as $pr) {
            $message .= $this->formatPr($pr);
        }

        return $message;
    }

    private function formatPr(Pr $pr): string
    {
        $commits = implode(';', array_map(fn (Commit $commit) => sprintf('"%s"%s', $commit->getSha(), $commit->isConflict() ? ' (with conflict)' : ''), $pr->getCommits()));

        return sprintf(
            '-   #!%s, #%s, "%s", %s, [%s] %s',
            $pr->getId(),
            $pr->getSelectorId(),
            $pr->getSelectorTitle(),
            $pr->getCommitsCount(),
            $commits,
            PHP_EOL
        );
    }
}
