<?php

namespace Ezdeliver\Tests\Vcs\Result;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;
use Ezdeliver\Vcs\Result\GlobalMergeResult;
use Ezdeliver\Vcs\Result\MergeResult;
use PHPUnit\Framework\TestCase;

class GlobalMergeResultTest extends TestCase
{
    private function makePr(): Pr
    {
        return new Pr(1, 'PR title', new Issue(10, 'issue', []), []);
    }

    private function makeCommit(): Commit
    {
        return new Commit('abc123', 'message', new \DateTimeImmutable());
    }

    public function testSuccessState(): void
    {
        $result = GlobalMergeResult::success();

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isOnError());
        $this->assertFalse($result->isConflicting());
    }

    public function testErrorStateWithProblematicPr(): void
    {
        $pr = $this->makePr();
        $result = GlobalMergeResult::error($pr);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isOnError());
        $this->assertFalse($result->isConflicting());
        $this->assertSame($pr, $result->getProblematicPr());
    }

    public function testConflictStateWithPrAndCommit(): void
    {
        $pr = $this->makePr();
        $commit = $this->makeCommit();
        $mergeResult = MergeResult::conflict($commit);
        $result = GlobalMergeResult::conflict($mergeResult, $pr);

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isOnError());
        $this->assertTrue($result->isConflicting());
        $this->assertSame($pr, $result->getProblematicPr());
        $this->assertSame($commit, $result->getConflictingCommit());
    }

    public function testGetProblematicPrThrowsOnSuccessState(): void
    {
        $this->expectException(\LogicException::class);

        GlobalMergeResult::success()->getProblematicPr();
    }

    public function testGetProblematicMergeResultThrowsOnSuccessState(): void
    {
        $this->expectException(\LogicException::class);

        GlobalMergeResult::success()->getProblematicMergeResult();
    }

    public function testGetProblematicMergeResultThrowsOnErrorState(): void
    {
        $this->expectException(\LogicException::class);

        GlobalMergeResult::error($this->makePr())->getProblematicMergeResult();
    }
}
