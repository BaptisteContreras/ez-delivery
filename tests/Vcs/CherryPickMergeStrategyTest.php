<?php

namespace Ezdeliver\Tests\Vcs;

use Castor\Context;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Selector;
use Ezdeliver\Vcs\CherryPickMergeStrategy;
use Ezdeliver\Vcs\GitDriver;
use Ezdeliver\Vcs\Result\CherryPickResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class CherryPickMergeStrategyTest extends TestCase
{
    private function makeCommit(string $sha, bool $handled = false): Commit
    {
        $commit = new Commit($sha, "message for $sha", new \DateTimeImmutable());
        if ($handled) {
            $commit->markHandled();
        }

        return $commit;
    }

    private function makePr(array $commits): Pr
    {
        return new Pr(1, 'PR title', new Selector(10, 'issue', []), $commits);
    }

    private function makeStrategy(): array
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $io = $this->createMock(SymfonyStyle::class);
        $strategy = new CherryPickMergeStrategy($gitDriver, $io);

        return [$strategy, $gitDriver];
    }

    public function testSuccessfulCherryPickReturnsSuccess(): void
    {
        [$strategy, $gitDriver] = $this->makeStrategy();
        $commit = $this->makeCommit('abc123');
        $pr = $this->makePr([$commit]);

        $gitDriver->method('cherryPick')
            ->willReturn(new CherryPickResult(true, '', ''));

        $result = $strategy->mergePr(new Context(), $pr);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($commit->isHandled());
    }

    public function testAlreadyHandledCommitsAreSkipped(): void
    {
        [$strategy, $gitDriver] = $this->makeStrategy();
        $commit = $this->makeCommit('abc123', handled: true);
        $pr = $this->makePr([$commit]);

        $gitDriver->expects($this->never())->method('cherryPick');

        $result = $strategy->mergePr(new Context(), $pr);

        $this->assertTrue($result->isSuccess());
    }

    public function testEmptyCherryPickIsSkippedAndContinues(): void
    {
        [$strategy, $gitDriver] = $this->makeStrategy();
        $commit1 = $this->makeCommit('sha1');
        $commit2 = $this->makeCommit('sha2');
        $pr = $this->makePr([$commit1, $commit2]);

        $gitDriver->method('cherryPick')
            ->willReturnOnConsecutiveCalls(
                new CherryPickResult(false, 'if the commit is empty use --allow-empty', ''),
                new CherryPickResult(true, '', ''),
            );
        $gitDriver->expects($this->once())->method('skipCkerryPick');

        $result = $strategy->mergePr(new Context(), $pr);

        $this->assertTrue($result->isSuccess());
    }

    public function testConflictInOutputReturnsConflictResultAndMarksCommit(): void
    {
        [$strategy, $gitDriver] = $this->makeStrategy();
        $commit = $this->makeCommit('sha1');
        $pr = $this->makePr([$commit]);

        $gitDriver->method('cherryPick')
            ->willReturn(new CherryPickResult(false, '', 'CONFLICT (content): Merge conflict in src/Foo.php'));

        $result = $strategy->mergePr(new Context(), $pr);

        $this->assertTrue($result->isConflicting());
        $this->assertSame($commit, $result->getConflictingCommit());
        $this->assertTrue($commit->isConflict());
    }

    public function testGenericFailureReturnsError(): void
    {
        [$strategy, $gitDriver] = $this->makeStrategy();
        $pr = $this->makePr([$this->makeCommit('sha1')]);

        $gitDriver->method('cherryPick')
            ->willReturn(new CherryPickResult(false, 'fatal: bad object sha1', 'some output without conflict'));

        $result = $strategy->mergePr(new Context(), $pr);

        $this->assertTrue($result->isOnError());
    }

    public function testVerboseLogsOutputOnSuccessfulCherryPick(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('cherry-pick output');

        $strategy = new CherryPickMergeStrategy($gitDriver, $io);
        $pr = $this->makePr([$this->makeCommit('abc123')]);

        $gitDriver->method('cherryPick')->willReturn(new CherryPickResult(true, '', 'cherry-pick output'));

        $strategy->mergePr(new Context(), $pr);
    }

    public function testNoVerboseCommentOnSuccessWhenNotVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(false);
        $io->expects($this->never())->method('comment');

        $strategy = new CherryPickMergeStrategy($gitDriver, $io);
        $pr = $this->makePr([$this->makeCommit('abc123')]);

        $gitDriver->method('cherryPick')->willReturn(new CherryPickResult(true, '', 'cherry-pick output'));

        $strategy->mergePr(new Context(), $pr);
    }

    public function testVerboseLogsOutputOnConflict(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('CONFLICT (content): Merge conflict in src/Foo.php');

        $strategy = new CherryPickMergeStrategy($gitDriver, $io);
        $pr = $this->makePr([$this->makeCommit('sha1')]);

        $gitDriver->method('cherryPick')->willReturn(new CherryPickResult(false, '', 'CONFLICT (content): Merge conflict in src/Foo.php'));

        $strategy->mergePr(new Context(), $pr);
    }

    public function testVerboseLogsOutputOnSkip(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('skipCkerryPick')->willReturn('skip output');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('skip output');

        $strategy = new CherryPickMergeStrategy($gitDriver, $io);
        $pr = $this->makePr([$this->makeCommit('sha1')]);

        $gitDriver->method('cherryPick')->willReturn(new CherryPickResult(false, 'if the commit is empty use --allow-empty', ''));

        $strategy->mergePr(new Context(), $pr);
    }

    public function testApplyConflictResolutionLogsOutputWhenVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('continueCkerryPick')->willReturn('continue output');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io->expects($this->once())->method('comment')->with('continue output');

        (new CherryPickMergeStrategy($gitDriver, $io))->applyConflictResolution(new Context());
    }

    public function testApplyConflictResolutionDoesNotLogWhenNotVerbose(): void
    {
        $gitDriver = $this->createMock(GitDriver::class);
        $gitDriver->method('continueCkerryPick')->willReturn('continue output');

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(false);
        $io->expects($this->never())->method('comment');

        (new CherryPickMergeStrategy($gitDriver, $io))->applyConflictResolution(new Context());
    }
}
