<?php

namespace Ezdeliver\Tests\Vcs;

use Castor\Context;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Selector;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\GitWorkspace;
use Ezdeliver\Vcs\MergeStrategyInterface;
use Ezdeliver\Vcs\Result\MergeResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class GitWorkspaceTest extends TestCase
{
    private function makeWorkspace(?GitDriver $gitDriver = null, ?MergeStrategyInterface $strategy = null, ?SymfonyStyle $io = null): GitWorkspace
    {
        return new GitWorkspace(
            $gitDriver ?? $this->createMock(GitDriver::class),
            new Context(),
            $strategy ?? $this->createMock(MergeStrategyInterface::class),
            $io ?? $this->createMock(SymfonyStyle::class),
        );
    }

    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    private function makePr(int $id, array $commits = []): Pr
    {
        return new Pr($id, "PR #$id", new Selector($id * 10, 'issue', []), $commits);
    }

    public function testIsClearReturnsTrueWhenNothingToCommit(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('status')->willReturn('nothing to commit, working tree clean');

        $workspace = $this->makeWorkspace($gitDriver);

        $this->assertTrue($workspace->isClear());
    }

    public function testIsClearReturnsFalseWhenModifiedFiles(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('status')->willReturn("modified:   src/Foo.php\n");

        $workspace = $this->makeWorkspace($gitDriver);

        $this->assertFalse($workspace->isClear());
    }

    public function testHasChangesToBeCommitedReturnsTrueWhenStagedChanges(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('status')->willReturn("Changes to be committed:\n  modified: src/Foo.php");

        $workspace = $this->makeWorkspace($gitDriver);

        $this->assertTrue($workspace->hasChangesToBeCommited());
    }

    public function testHasChangesToBeCommitedReturnsFalseWhenNoStagedChanges(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('status')->willReturn('nothing to commit, working tree clean');

        $workspace = $this->makeWorkspace($gitDriver);

        $this->assertFalse($workspace->hasChangesToBeCommited());
    }

    public function testMergePrsSkipsAlreadyHandledPrs(): void
    {
        $strategy = $this->createMock(MergeStrategyInterface::class);
        $strategy->expects($this->never())->method('mergePr');

        $pr = $this->makePr(1);
        $pr->markHandled();

        $result = $this->makeWorkspace(strategy: $strategy)->mergePrs([$pr]);

        $this->assertTrue($result->isSuccess());
    }

    public function testMergePrsMarksEachPrHandledOnSuccess(): void
    {
        $strategy = $this->createMock(MergeStrategyInterface::class);
        $strategy->method('mergePr')->willReturn(MergeResult::success());

        $pr1 = $this->makePr(1);
        $pr2 = $this->makePr(2);

        $this->makeWorkspace(strategy: $strategy)->mergePrs([$pr1, $pr2]);

        $this->assertTrue($pr1->isHandled());
        $this->assertTrue($pr2->isHandled());
    }

    public function testMergePrsReturnsConflictImmediatelyOnFirstConflictingPr(): void
    {
        $commit = $this->makeCommit('sha-conflict');
        $strategy = $this->createMock(MergeStrategyInterface::class);
        $strategy->method('mergePr')
            ->willReturnOnConsecutiveCalls(
                MergeResult::success(),
                MergeResult::conflict($commit),
            );

        $pr1 = $this->makePr(1);
        $pr2 = $this->makePr(2, [$commit]);
        $pr3 = $this->makePr(3);

        $result = $this->makeWorkspace(strategy: $strategy)->mergePrs([$pr1, $pr2, $pr3]);

        $this->assertTrue($result->isConflicting());
        $this->assertSame($pr2, $result->getProblematicPr());
        $this->assertFalse($pr3->isHandled());
    }

    public function testMergePrsReturnsErrorImmediatelyOnErrorPr(): void
    {
        $strategy = $this->createMock(MergeStrategyInterface::class);
        $strategy->method('mergePr')
            ->willReturnOnConsecutiveCalls(
                MergeResult::success(),
                MergeResult::error(),
            );

        $pr1 = $this->makePr(1);
        $pr2 = $this->makePr(2);
        $pr3 = $this->makePr(3);

        $result = $this->makeWorkspace(strategy: $strategy)->mergePrs([$pr1, $pr2, $pr3]);

        $this->assertTrue($result->isOnError());
        $this->assertSame($pr2, $result->getProblematicPr());
        $this->assertFalse($pr3->isHandled());
    }

    public function testAddGitReleaseInfoCommitsWithPrDetails(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $commit = $this->makeCommit('sha-abc');
        $pr = $this->makePr(42, [$commit]);

        $capturedMessage = null;
        $gitDriver->expects($this->once())
            ->method('commit')
            ->willReturnCallback(function ($context, string $message, bool $allowEmpty) use (&$capturedMessage) {
                $capturedMessage = $message;

                return '';
            });

        $this->makeWorkspace($gitDriver)->addGitReleaseInfo([$pr]);

        $this->assertStringContainsString('#!42', $capturedMessage);
        $this->assertStringContainsString('sha-abc', $capturedMessage);
    }

    public function testAddGitReleaseInfoWrapsTitleAndShaInPlainQuotesWithoutBackslashes(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $commit = $this->makeCommit('sha-abc');
        $pr = new Pr(42, 'PR #42', new Selector(10, 'Fix the bug', []), [$commit]);

        $capturedMessage = null;
        $gitDriver->expects($this->once())
            ->method('commit')
            ->willReturnCallback(function ($context, string $message, bool $allowEmpty) use (&$capturedMessage) {
                $capturedMessage = $message;

                return '';
            });

        $this->makeWorkspace($gitDriver)->addGitReleaseInfo([$pr]);

        $this->assertStringContainsString('"Fix the bug"', $capturedMessage);
        $this->assertStringContainsString('"sha-abc"', $capturedMessage);
        $this->assertStringNotContainsString('\\"', $capturedMessage);
    }

    public function testReleaseMessageWithQuotedIssueTitleStillReachesGitAsASingleSafeArgument(): void
    {
        // Regression check: this fix makes addPrInfo() embed a literal, unescaped `"` in the
        // release message around titles/SHAs. That is only safe because GitDriver::commit()
        // builds the git command as an argument array rather than a shell string - confirm that
        // chain still holds even when the issue title itself legitimately contains a `"`,
        // i.e. that the older double-quote-in-title fix (GitDriverTest) wasn't undone by this one.
        $pr = new Pr(1, 'PR #1', new Selector(10, 'Fix the "login" bug', []), [$this->makeCommit('sha-abc')]);

        $addPrInfo = new \ReflectionMethod(GitWorkspace::class, 'addPrInfo');
        $addPrInfo->setAccessible(true);
        $message = $addPrInfo->invoke($this->makeWorkspace(), $pr, '');

        $buildCommitCommand = new \ReflectionMethod(GitDriver::class, 'buildCommitCommand');
        $buildCommitCommand->setAccessible(true);
        $command = $buildCommitCommand->invoke(new GitDriver(), $message, true);

        $this->assertStringContainsString('"Fix the "login" bug"', $message);
        $this->assertSame(['git', 'commit', '-m', $message, '--allow-empty'], $command);
    }

    public function testIsClearLogsStatusOutputWhenVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('status')->willReturn('nothing to commit, working tree clean');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('git status: nothing to commit, working tree clean');

        $this->makeWorkspace($gitDriver, io: $io)->isClear();
    }

    public function testIsClearDoesNotLogWhenNotVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('status')->willReturn('nothing to commit, working tree clean');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(false);
        $io->expects($this->never())->method('comment');

        $this->makeWorkspace($gitDriver, io: $io)->isClear();
    }

    public function testGetStatusLogsOutputWhenVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('status')->willReturn('modified: src/Foo.php');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('git status: modified: src/Foo.php');

        $this->makeWorkspace($gitDriver, io: $io)->getStatus();
    }

    public function testUpdateAndCheckoutBranchLogsEachCommandOutputWhenVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('fetchAll')->willReturn('fetch output');
        $gitDriver->method('checkout')->willReturn('checkout output');
        $gitDriver->method('pull')->willReturn('pull output');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);

        $comments = [];
        $io->expects($this->exactly(3))
            ->method('comment')
            ->willReturnCallback(function ($line) use (&$comments) {
                $comments[] = $line;
            });

        $this->makeWorkspace($gitDriver, io: $io)->updateAndCheckoutBranch('main');

        $this->assertSame('git fetch --all: fetch output', $comments[0]);
        $this->assertSame('git checkout: checkout output', $comments[1]);
        $this->assertSame('git pull --rebase: pull output', $comments[2]);
    }

    public function testCreateAndCheckoutBranchLogsOutputWhenVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('checkout')->willReturn('checkout -b output');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('git checkout -b: checkout -b output');

        $this->makeWorkspace($gitDriver, io: $io)->createAndCheckoutBranch('feature-branch');
    }

    public function testAddGitReleaseInfoLogsCommitOutputWhenVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('commit')->willReturn('commit output');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('git commit: commit output');

        $pr = $this->makePr(1, [$this->makeCommit('sha-abc')]);

        $this->makeWorkspace($gitDriver, io: $io)->addGitReleaseInfo([$pr]);
    }

    public function testPushReleaseLogsCommandWhenVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('git push --set-upstream origin release-branch');

        $this->makeWorkspace($gitDriver, io: $io)->pushRelease('release-branch');
    }

    public function testPushReleaseDoesNotLogWhenNotVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(false);
        $io->expects($this->never())->method('comment');

        $this->makeWorkspace($gitDriver, io: $io)->pushRelease('release-branch');
    }
}
