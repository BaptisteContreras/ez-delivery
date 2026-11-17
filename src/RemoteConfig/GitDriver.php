<?php

namespace Ezdeliver\RemoteConfig;

use Castor\Context;

use function Castor\capture;

class GitDriver
{
    public function clone(Context $context, string $url, string $targetDir): string
    {
        return capture($this->buildCloneCommand($url, $targetDir), context: $context);
    }

    public function fetch(Context $context): string
    {
        return capture(['git', 'fetch', 'origin'], context: $context);
    }

    /**
     * Discards every local change: tracked edits, unpushed commits, and untracked files.
     */
    public function fetchAndHardResetToUpstream(Context $context): string
    {
        $fetch = $this->fetch($context);
        $reset = capture(['git', 'reset', '--hard', '@{u}'], context: $context);
        $clean = capture(['git', 'clean', '-fd'], context: $context);

        return $fetch.PHP_EOL.$reset.PHP_EOL.$clean;
    }

    /**
     * Porcelain status (tracked and untracked changes), optionally limited to one path.
     * Empty string means clean.
     */
    public function statusPorcelain(Context $context, ?string $path = null): string
    {
        $command = ['git', 'status', '--porcelain'];

        if (null !== $path) {
            array_push($command, '--', $path);
        }

        return capture($command, context: $context);
    }

    /**
     * One line per local commit not yet on the upstream branch. Empty string means none.
     */
    public function unpushedCommits(Context $context): string
    {
        return capture(['git', 'log', '--oneline', '@{u}..HEAD'], context: $context);
    }

    public function unpushedDiff(Context $context): string
    {
        return capture(['git', 'diff', '@{u}..HEAD'], context: $context);
    }

    public function countCommitsBehindUpstream(Context $context): int
    {
        return (int) capture(['git', 'rev-list', '--count', 'HEAD..@{u}'], context: $context);
    }

    /**
     * Stages and commits a single path, leaving any other change in the working tree untouched.
     */
    public function commitPath(Context $context, string $path, string $message): string
    {
        $add = capture(['git', 'add', '--', $path], context: $context);
        $commit = capture($this->buildCommitPathCommand($path, $message), context: $context);

        return $add.PHP_EOL.$commit;
    }

    public function push(Context $context): string
    {
        return capture(['git', 'push', 'origin', 'HEAD'], context: $context);
    }

    /**
     * @return array<string>
     */
    private function buildCloneCommand(string $url, string $targetDir): array
    {
        return ['git', 'clone', '--depth', '1', $url, $targetDir];
    }

    /**
     * @return array<string>
     */
    private function buildCommitPathCommand(string $path, string $message): array
    {
        return ['git', 'commit', '-m', $message, '--', $path];
    }
}
