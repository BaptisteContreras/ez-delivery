<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;
use Ezdeliver\Repo\IssueReferenceStrategy;
use Ezdeliver\Repo\NullReferenceStrategy;
use PHPUnit\Framework\TestCase;

class PrReferenceStrategyTest extends TestCase
{
    private function makePr(?PrReference $reference): Pr
    {
        return new Pr(1, 'PR title', [], $reference, []);
    }

    public function testIssueReferenceStrategySupportsReference(): void
    {
        $this->assertTrue((new IssueReferenceStrategy())->supportsReference());
    }

    public function testIssueReferenceStrategyResolveReturnsPrReference(): void
    {
        $reference = new PrReference(10, 'issue title');
        $pr = $this->makePr($reference);

        $this->assertSame($reference, (new IssueReferenceStrategy())->resolve($pr));
    }

    public function testIssueReferenceStrategyResolveReturnsNullWhenPrHasNoReference(): void
    {
        $pr = $this->makePr(null);

        $this->assertNull((new IssueReferenceStrategy())->resolve($pr));
    }

    public function testNullReferenceStrategyDoesNotSupportReference(): void
    {
        $this->assertFalse((new NullReferenceStrategy())->supportsReference());
    }

    public function testNullReferenceStrategyResolveAlwaysReturnsNull(): void
    {
        $pr = $this->makePr(new PrReference(10, 'issue title'));

        $this->assertNull((new NullReferenceStrategy())->resolve($pr));
    }
}
