<?php

namespace Ezdeliver\Tests\Vcs\Result;

use Ezdeliver\Model\Commit;
use Ezdeliver\Vcs\Result\MergeResult;
use PHPUnit\Framework\TestCase;

class MergeResultTest extends TestCase
{
    private function makeCommit(): Commit
    {
        return new Commit('abc123', 'message', new \DateTimeImmutable());
    }

    public function testSuccessState(): void
    {
        $result = MergeResult::success();

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isOnError());
        $this->assertFalse($result->isConflicting());
    }

    public function testErrorState(): void
    {
        $result = MergeResult::error();

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isOnError());
        $this->assertFalse($result->isConflicting());
    }

    public function testConflictStateWithCommit(): void
    {
        $commit = $this->makeCommit();
        $result = MergeResult::conflict($commit);

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isOnError());
        $this->assertTrue($result->isConflicting());
        $this->assertSame($commit, $result->getConflictingCommit());
    }

    public function testGetConflictingCommitThrowsOnSuccessState(): void
    {
        $this->expectException(\LogicException::class);

        MergeResult::success()->getConflictingCommit();
    }

    public function testGetConflictingCommitThrowsOnErrorState(): void
    {
        $this->expectException(\LogicException::class);

        MergeResult::error()->getConflictingCommit();
    }
}
