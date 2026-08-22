<?php

namespace Ezdeliver\Tests\RemoteConfig;

use Ezdeliver\RemoteConfig\GitDriver;
use PHPUnit\Framework\TestCase;

class GitDriverTest extends TestCase
{
    private function buildCloneCommand(GitDriver $driver, string $url, string $targetDir): array
    {
        $method = new \ReflectionMethod(GitDriver::class, 'buildCloneCommand');
        $method->setAccessible(true);

        return $method->invoke($driver, $url, $targetDir);
    }

    public function testCloneBuildsAShallowCloneCommandAsAnArgumentArray(): void
    {
        // Building the command as an argument array (not a shell string) means a git URL or
        // target path containing shell-special characters reaches git untouched — the same
        // reasoning Vcs\GitDriver::buildCommitCommand applies to commit messages.
        $command = $this->buildCloneCommand(new GitDriver(), 'git@gitlab.com:team/configs.git', '/tmp/remote-repo-clone');

        $this->assertSame(['git', 'clone', '--depth', '1', 'git@gitlab.com:team/configs.git', '/tmp/remote-repo-clone'], $command);
    }
}
