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

    public function fetchAndHardResetToUpstream(Context $context): string
    {
        $fetch = capture('git fetch origin', context: $context);
        $reset = capture('git reset --hard @{u}', context: $context);

        return $fetch.PHP_EOL.$reset;
    }

    /**
     * @return array<string>
     */
    private function buildCloneCommand(string $url, string $targetDir): array
    {
        return ['git', 'clone', '--depth', '1', $url, $targetDir];
    }
}
