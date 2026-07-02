<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\PrReference;
use PHPUnit\Framework\TestCase;

class PrReferenceTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $reference = new PrReference(42, 'Fix the bug');

        $this->assertSame(42, $reference->getId());
        $this->assertSame('Fix the bug', $reference->getTitle());
    }
}
