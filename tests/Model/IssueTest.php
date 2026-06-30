<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\Issue;
use PHPUnit\Framework\TestCase;

class IssueTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $issue = new Issue(42, 'Fix the bug', ['bug', 'priority:high']);

        $this->assertSame(42, $issue->getId());
        $this->assertSame('Fix the bug', $issue->getTitle());
        $this->assertSame(['bug', 'priority:high'], $issue->getLabels());
    }

    public function testHasLabelReturnsTrueWhenPresent(): void
    {
        $issue = new Issue(1, 'title', ['to-deliver', 'bug']);

        $this->assertTrue($issue->hasLabel('to-deliver'));
    }

    public function testHasLabelReturnsFalseWhenAbsent(): void
    {
        $issue = new Issue(1, 'title', ['bug']);

        $this->assertFalse($issue->hasLabel('to-deliver'));
    }

    public function testHasLabelReturnsFalseOnEmptyLabels(): void
    {
        $issue = new Issue(1, 'title', []);

        $this->assertFalse($issue->hasLabel('to-deliver'));
    }
}
