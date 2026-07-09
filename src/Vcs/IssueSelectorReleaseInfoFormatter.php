<?php

namespace Ezdeliver\Vcs;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;

final class IssueSelectorReleaseInfoFormatter implements PrReleaseInfoFormatter
{
    public function format(Pr $pr): string
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
