<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;
use Ezdeliver\Repo\IssueReferenceStrategy;
use Ezdeliver\Repo\NullReferenceStrategy;
use PHPUnit\Framework\TestCase;

class PrReferenceStrategyTest extends TestCase
{
    private function makePr(?PrReference $reference, array $labels = []): Pr
    {
        return new Pr(1, 'PR title', $labels, $reference, []);
    }

    public function testIssueReferenceStrategySupportsReference(): void
    {
        $this->assertTrue((new IssueReferenceStrategy())->supportsReference());
    }

    public function testIssueReferenceStrategyResolveReturnsPrReference(): void
    {
        $reference = new PrReference(10, 'issue title', []);
        $pr = $this->makePr($reference);

        $this->assertSame($reference, (new IssueReferenceStrategy())->resolve($pr));
    }

    public function testIssueReferenceStrategyResolveReturnsNullWhenPrHasNoReference(): void
    {
        $pr = $this->makePr(null);

        $this->assertNull((new IssueReferenceStrategy())->resolve($pr));
    }

    public function testIssueReferenceStrategyResolveLabelsReturnsReferenceLabels(): void
    {
        $reference = new PrReference(10, 'issue title', ['to-deliver:staging', 'bug']);
        $pr = $this->makePr($reference, ['unrelated-pr-label']);

        $this->assertSame(['to-deliver:staging', 'bug'], (new IssueReferenceStrategy())->resolveLabels($pr));
    }

    public function testIssueReferenceStrategyResolveLabelsReturnsEmptyArrayWhenNoReference(): void
    {
        $pr = $this->makePr(null, ['unrelated-pr-label']);

        $this->assertSame([], (new IssueReferenceStrategy())->resolveLabels($pr));
    }

    public function testNullReferenceStrategyDoesNotSupportReference(): void
    {
        $this->assertFalse((new NullReferenceStrategy())->supportsReference());
    }

    public function testNullReferenceStrategyResolveAlwaysReturnsNull(): void
    {
        $pr = $this->makePr(new PrReference(10, 'issue title', []));

        $this->assertNull((new NullReferenceStrategy())->resolve($pr));
    }

    public function testNullReferenceStrategyResolveLabelsReturnsPrOwnLabels(): void
    {
        $pr = $this->makePr(new PrReference(10, 'issue title', ['issue-label']), ['to-deliver:staging', 'bug']);

        $this->assertSame(['to-deliver:staging', 'bug'], (new NullReferenceStrategy())->resolveLabels($pr));
    }
}
