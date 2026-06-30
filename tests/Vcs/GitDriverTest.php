<?php

namespace Ezdeliver\Tests\Vcs;

use Ezdeliver\Vcs\GitDriver;
use PHPUnit\Framework\TestCase;

class GitDriverTest extends TestCase
{
    private function buildCommitCommand(GitDriver $driver, string $message, bool $allowEmpty): array
    {
        $method = new \ReflectionMethod(GitDriver::class, 'buildCommitCommand');
        $method->setAccessible(true);

        return $method->invoke($driver, $message, $allowEmpty);
    }

    public function testCommitPassesAMessageContainingDoubleQuotesAsASingleArgument(): void
    {
        // A title fetched from Gitlab/Github can legitimately contain a double quote
        // (e.g. `Fix the "login" bug`). Building the git command as a shell string with
        // the message embedded between literal quotes lets that character break out of
        // the shell quoting, corrupting the command. Building it as an argument array
        // instead bypasses the shell entirely, so the message reaches git untouched.
        $command = $this->buildCommitCommand(new GitDriver(), 'Fix the "login" bug', true);

        $this->assertSame(['git', 'commit', '-m', 'Fix the "login" bug', '--allow-empty'], $command);
    }

    public function testCommitOmitsAllowEmptyFlagWhenNotAllowed(): void
    {
        $command = $this->buildCommitCommand(new GitDriver(), 'Regular commit message', false);

        $this->assertSame(['git', 'commit', '-m', 'Regular commit message'], $command);
    }
}
