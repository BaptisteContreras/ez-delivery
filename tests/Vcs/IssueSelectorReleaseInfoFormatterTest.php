<?php

namespace Ezdeliver\Tests\Vcs;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Selector;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\IssueSelectorReleaseInfoFormatter;
use PHPUnit\Framework\TestCase;

class IssueSelectorReleaseInfoFormatterTest extends TestCase
{
    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    public function testFormatReturnsExpectedShape(): void
    {
        $pr = new Pr(42, 'PR title', new Selector(7, 'Selector title', []), [$this->makeCommit('sha1')]);

        $message = (new IssueSelectorReleaseInfoFormatter())->format($pr);

        $this->assertSame(
            sprintf('-   #!%s, #%s, "%s", %s, [%s] %s', 42, 7, 'Selector title', 1, '"sha1"', PHP_EOL),
            $message
        );
    }

    public function testFormatWrapsTitleAndShaInPlainQuotesWithoutBackslashes(): void
    {
        $pr = new Pr(42, 'PR #42', new Selector(10, 'Fix the bug', []), [$this->makeCommit('sha-abc')]);

        $message = (new IssueSelectorReleaseInfoFormatter())->format($pr);

        $this->assertStringContainsString('"Fix the bug"', $message);
        $this->assertStringContainsString('"sha-abc"', $message);
        $this->assertStringNotContainsString('\\"', $message);
    }

    public function testFormatMessageWithQuotedTitleStillReachesGitAsASingleSafeArgument(): void
    {
        // Regression check: format() embeds a literal, unescaped `"` in the release message
        // around titles/SHAs. That is only safe because GitDriver::commit() builds the git
        // command as an argument array rather than a shell string - confirm that chain still
        // holds even when the selector title itself legitimately contains a `"`, i.e. that the
        // older double-quote-in-title fix (GitDriverTest) wasn't undone by this one.
        $pr = new Pr(1, 'PR #1', new Selector(10, 'Fix the "login" bug', []), [$this->makeCommit('sha-abc')]);

        $message = (new IssueSelectorReleaseInfoFormatter())->format($pr);

        $buildCommitCommand = new \ReflectionMethod(GitDriver::class, 'buildCommitCommand');
        $buildCommitCommand->setAccessible(true);
        $command = $buildCommitCommand->invoke(new GitDriver(), $message, true);

        $this->assertStringContainsString('"Fix the "login" bug"', $message);
        $this->assertSame(['git', 'commit', '-m', $message, '--allow-empty'], $command);
    }
}
