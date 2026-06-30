<?php

namespace Ezdeliver\Tests\Vcs;

use Castor\Context;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\GitWorkspace;
use Ezdeliver\Vcs\MergeStrategyInterface;
use Ezdeliver\Vcs\Result\MergeResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class GitWorkspaceTest extends TestCase
{
    private function makeWorkspace(?GitDriver $gitDriver = null, ?MergeStrategyInterface $strategy = null): GitWorkspace
    {
        return new GitWorkspace(
            $gitDriver ?? $this->createMock(GitDriver::class),
            new Context(),
            $strategy ?? $this->createMock(MergeStrategyInterface::class),
            $this->createMock(SymfonyStyle::class),
        );
    }

    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    private function makePr(int $id, array $commits = []): Pr
    {
        return new Pr($id, "PR #$id", new Issue($id * 10, 'issue', []), $commits);
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
            });

        $this->makeWorkspace($gitDriver)->addGitReleaseInfo([$pr]);

        $this->assertStringContainsString('#!42', $capturedMessage);
        $this->assertStringContainsString('sha-abc', $capturedMessage);
    }
}
