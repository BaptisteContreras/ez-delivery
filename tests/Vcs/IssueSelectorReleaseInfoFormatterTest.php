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

    public function testFormatReleaseMessageReturnsExpectedShape(): void
    {
        $pr = new Pr(42, 'PR title', new Selector(7, 'Selector title', []), [$this->makeCommit('sha1')]);

        $message = (new IssueSelectorReleaseInfoFormatter())->formatReleaseMessage([$pr]);

        $expected = sprintf('AUTO RELEASE %s', PHP_EOL)
            .sprintf('Number of PRs delivered : %s %s', 1, PHP_EOL)
            .sprintf('PR #ID, Issue #ID, Issue title, Number of commit, [Commits] %s%s%s', PHP_EOL, PHP_EOL, PHP_EOL)
            .sprintf('-   #!%s, #%s, "%s", %s, [%s] %s', 42, 7, 'Selector title', 1, '"sha1"', PHP_EOL);

        $this->assertSame($expected, $message);
    }

    public function testFormatReleaseMessageWrapsTitleAndShaInPlainQuotesWithoutBackslashes(): void
    {
        $pr = new Pr(42, 'PR #42', new Selector(10, 'Fix the bug', []), [$this->makeCommit('sha-abc')]);

        $message = (new IssueSelectorReleaseInfoFormatter())->formatReleaseMessage([$pr]);

        $this->assertStringContainsString('"Fix the bug"', $message);
        $this->assertStringContainsString('"sha-abc"', $message);
        $this->assertStringNotContainsString('\\"', $message);
    }

    public function testFormatReleaseMessageWithQuotedTitleStillReachesGitAsASingleSafeArgument(): void
    {
        // Regression check: formatReleaseMessage() embeds a literal, unescaped `"` in the release
        // message around titles/SHAs. That is only safe because GitDriver::commit() builds the git
        // command as an argument array rather than a shell string - confirm that chain still
        // holds even when the selector title itself legitimately contains a `"`, i.e. that the
        // older double-quote-in-title fix (GitDriverTest) wasn't undone by this one.
        $pr = new Pr(1, 'PR #1', new Selector(10, 'Fix the "login" bug', []), [$this->makeCommit('sha-abc')]);

        $message = (new IssueSelectorReleaseInfoFormatter())->formatReleaseMessage([$pr]);

        $buildCommitCommand = new \ReflectionMethod(GitDriver::class, 'buildCommitCommand');
        $buildCommitCommand->setAccessible(true);
        $command = $buildCommitCommand->invoke(new GitDriver(), $message, true);

        $this->assertStringContainsString('"Fix the "login" bug"', $message);
        $this->assertSame(['git', 'commit', '-m', $message, '--allow-empty'], $command);
    }

    public function testFormatReleaseMessageConcatenatesOneLinePerPr(): void
    {
        $prOne = new Pr(1, 'First', new Selector(10, 'Issue A', []), [$this->makeCommit('sha1')]);
        $prTwo = new Pr(2, 'Second', new Selector(20, 'Issue B', []), []);

        $message = (new IssueSelectorReleaseInfoFormatter())->formatReleaseMessage([$prOne, $prTwo]);

        $this->assertStringContainsString('Number of PRs delivered : 2', $message);
        $this->assertStringContainsString(sprintf('-   #!%s, #%s, "%s", %s, [%s] %s', 1, 10, 'Issue A', 1, '"sha1"', PHP_EOL), $message);
        $this->assertStringContainsString(sprintf('-   #!%s, #%s, "%s", %s, [%s] %s', 2, 20, 'Issue B', 0, '', PHP_EOL), $message);
    }
}
