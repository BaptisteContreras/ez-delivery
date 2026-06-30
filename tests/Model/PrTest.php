<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;
use PHPUnit\Framework\TestCase;

class PrTest extends TestCase
{
    private function makeIssue(array $labels = []): Issue
    {
        return new Issue(10, 'issue title', $labels);
    }

    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    public function testGettersReturnConstructorValues(): void
    {
        $issue = $this->makeIssue(['bug']);
        $commits = [$this->makeCommit('sha1'), $this->makeCommit('sha2')];
        $pr = new Pr(5, 'Fix bug', $issue, $commits);

        $this->assertSame(5, $pr->getId());
        $this->assertSame('Fix bug', $pr->getTitle());
        $this->assertSame(10, $pr->getClosingIssueId());
        $this->assertSame('issue title', $pr->getClosingIssueTitle());
        $this->assertSame($issue, $pr->getClosingIssue());
        $this->assertSame($commits, $pr->getCommits());
    }

    public function testGetCommitsCountReturnsNumberOfCommits(): void
    {
        $pr = new Pr(1, 'title', $this->makeIssue(), [
            $this->makeCommit('sha1'),
            $this->makeCommit('sha2'),
            $this->makeCommit('sha3'),
        ]);

        $this->assertSame(3, $pr->getCommitsCount());
    }

    public function testIsHandledStartsFalse(): void
    {
        $pr = new Pr(1, 'title', $this->makeIssue(), []);

        $this->assertFalse($pr->isHandled());
    }

    public function testMarkHandledSetsTrue(): void
    {
        $pr = new Pr(1, 'title', $this->makeIssue(), []);
        $pr->markHandled();

        $this->assertTrue($pr->isHandled());
    }

    public function testHasClosingIssueWithLabelReturnsTrueWhenPresent(): void
    {
        $pr = new Pr(1, 'title', $this->makeIssue(['to-deliver']), []);

        $this->assertTrue($pr->hasClosingIssueWithLabel('to-deliver'));
    }

    public function testHasClosingIssueWithLabelReturnsFalseWhenAbsent(): void
    {
        $pr = new Pr(1, 'title', $this->makeIssue(['bug']), []);

        $this->assertFalse($pr->hasClosingIssueWithLabel('to-deliver'));
    }
}
