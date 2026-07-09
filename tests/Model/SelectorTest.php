<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\Selector;
use PHPUnit\Framework\TestCase;

class SelectorTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $selector = new Selector(42, 'Fix the bug', ['bug', 'priority:high']);

        $this->assertSame(42, $selector->getId());
        $this->assertSame('Fix the bug', $selector->getTitle());
        $this->assertSame(['bug', 'priority:high'], $selector->getLabels());
    }

    public function testHasLabelReturnsTrueWhenPresent(): void
    {
        $selector = new Selector(1, 'title', ['to-deliver', 'bug']);

        $this->assertTrue($selector->hasLabel('to-deliver'));
    }

    public function testHasLabelReturnsFalseWhenAbsent(): void
    {
        $selector = new Selector(1, 'title', ['bug']);

        $this->assertFalse($selector->hasLabel('to-deliver'));
    }

    public function testHasLabelReturnsFalseOnEmptyLabels(): void
    {
        $selector = new Selector(1, 'title', []);

        $this->assertFalse($selector->hasLabel('to-deliver'));
    }
}
