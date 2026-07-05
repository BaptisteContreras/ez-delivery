<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\PrReference;
use PHPUnit\Framework\TestCase;

class PrReferenceTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $reference = new PrReference(42, 'Fix the bug', ['bug']);

        $this->assertSame(42, $reference->getId());
        $this->assertSame('Fix the bug', $reference->getTitle());
        $this->assertSame(['bug'], $reference->getLabels());
    }

    public function testHasLabelReturnsTrueWhenPresent(): void
    {
        $reference = new PrReference(42, 'Fix the bug', ['to-deliver']);

        $this->assertTrue($reference->hasLabel('to-deliver'));
    }

    public function testHasLabelReturnsFalseWhenAbsent(): void
    {
        $reference = new PrReference(42, 'Fix the bug', ['bug']);

        $this->assertFalse($reference->hasLabel('to-deliver'));
    }
}
