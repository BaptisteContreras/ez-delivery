<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;
use PHPUnit\Framework\TestCase;

class PrTest extends TestCase
{
    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    public function testGettersReturnConstructorValues(): void
    {
        $reference = new PrReference(10, 'issue title');
        $commits = [$this->makeCommit('sha1'), $this->makeCommit('sha2')];
        $pr = new Pr(5, 'Fix bug', ['bug'], $reference, $commits);

        $this->assertSame(5, $pr->getId());
        $this->assertSame('Fix bug', $pr->getTitle());
        $this->assertSame(['bug'], $pr->getLabels());
        $this->assertSame($reference, $pr->getReference());
        $this->assertSame($commits, $pr->getCommits());
    }

    public function testGetReferenceReturnsNullWhenNoneGiven(): void
    {
        $pr = new Pr(1, 'title', [], null, []);

        $this->assertNull($pr->getReference());
    }

    public function testGetCommitsCountReturnsNumberOfCommits(): void
    {
        $pr = new Pr(1, 'title', [], null, [
            $this->makeCommit('sha1'),
            $this->makeCommit('sha2'),
            $this->makeCommit('sha3'),
        ]);

        $this->assertSame(3, $pr->getCommitsCount());
    }

    public function testIsHandledStartsFalse(): void
    {
        $pr = new Pr(1, 'title', [], null, []);

        $this->assertFalse($pr->isHandled());
    }

    public function testMarkHandledSetsTrue(): void
    {
        $pr = new Pr(1, 'title', [], null, []);
        $pr->markHandled();

        $this->assertTrue($pr->isHandled());
    }

    public function testHasLabelReturnsTrueWhenPresent(): void
    {
        $pr = new Pr(1, 'title', ['to-deliver'], null, []);

        $this->assertTrue($pr->hasLabel('to-deliver'));
    }

    public function testHasLabelReturnsFalseWhenAbsent(): void
    {
        $pr = new Pr(1, 'title', ['bug'], null, []);

        $this->assertFalse($pr->hasLabel('to-deliver'));
    }
}
