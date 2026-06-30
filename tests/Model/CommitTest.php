<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\Commit;
use PHPUnit\Framework\TestCase;

class CommitTest extends TestCase
{
    private function makeCommit(): Commit
    {
        return new Commit('abc123', 'feat: add feature', new \DateTimeImmutable('2024-01-15 10:00:00'));
    }

    public function testGettersReturnConstructorValues(): void
    {
        $commit = $this->makeCommit();

        $this->assertSame('abc123', $commit->getSha());
        $this->assertSame('feat: add feature', $commit->getMessage());
        $this->assertEquals(new \DateTimeImmutable('2024-01-15 10:00:00'), $commit->getDate());
    }

    public function testIsHandledStartsFalse(): void
    {
        $this->assertFalse($this->makeCommit()->isHandled());
    }

    public function testMarkHandledSetsTrue(): void
    {
        $commit = $this->makeCommit();
        $commit->markHandled();

        $this->assertTrue($commit->isHandled());
    }

    public function testIsConflictStartsFalse(): void
    {
        $this->assertFalse($this->makeCommit()->isConflict());
    }

    public function testMarkConflictedSetsTrue(): void
    {
        $commit = $this->makeCommit();
        $commit->markConflicted();

        $this->assertTrue($commit->isConflict());
    }
}
