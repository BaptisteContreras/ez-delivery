<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Core\Model\Commit;
use Ezdeliver\Core\Model\Pr;
use Ezdeliver\Core\Model\Selector;
use Ezdeliver\Factory\SfFactory;
use PHPUnit\Framework\TestCase;

class PrTest extends TestCase
{
    private function makeSelector(array $labels = []): Selector
    {
        return new Selector(10, 'issue title', $labels);
    }

    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    public function testGettersReturnConstructorValues(): void
    {
        $selector = $this->makeSelector(['bug']);
        $commits = [$this->makeCommit('sha1'), $this->makeCommit('sha2')];
        $pr = new Pr(5, 'Fix bug', $selector, $commits);

        $this->assertSame(5, $pr->getId());
        $this->assertSame('Fix bug', $pr->getTitle());
        $this->assertSame($selector, $pr->getSelector());
        $this->assertSame($commits, $pr->getCommits());
    }

    public function testGetCommitsCountReturnsNumberOfCommits(): void
    {
        $pr = new Pr(1, 'title', $this->makeSelector(), [
            $this->makeCommit('sha1'),
            $this->makeCommit('sha2'),
            $this->makeCommit('sha3'),
        ]);

        $this->assertSame(3, $pr->getCommitsCount());
    }

    public function testIsHandledStartsFalse(): void
    {
        $pr = new Pr(1, 'title', $this->makeSelector(), []);

        $this->assertFalse($pr->isHandled());
    }

    public function testMarkHandledSetsTrue(): void
    {
        $pr = new Pr(1, 'title', $this->makeSelector(), []);
        $pr->markHandled();

        $this->assertTrue($pr->isHandled());
    }

    public function testHasSelectorLabelReturnsTrueWhenPresent(): void
    {
        $pr = new Pr(1, 'title', $this->makeSelector(['to-deliver']), []);

        $this->assertTrue($pr->getSelector()->hasLabel('to-deliver'));
    }

    public function testHasSelectorLabelReturnsFalseWhenAbsent(): void
    {
        $pr = new Pr(1, 'title', $this->makeSelector(['bug']), []);

        $this->assertFalse($pr->getSelector()->hasLabel('to-deliver'));
    }

    public function testSelectorDelegationGettersReturnSelectorValues(): void
    {
        $pr = new Pr(1, 'title', $this->makeSelector(['bug', 'to-deliver']), []);

        $this->assertSame(10, $pr->getSelectorId());
        $this->assertSame('issue title', $pr->getSelectorTitle());
        $this->assertSame(['bug', 'to-deliver'], $pr->getSelectorLabels());
    }

    public function testMergeOrderIsNullByDefault(): void
    {
        $this->assertNull((new Pr(1, 't', $this->makeSelector(), []))->getMergeOrder());
    }

    public function testAssignMergeOrder(): void
    {
        $pr = new Pr(1, 't', $this->makeSelector(), []);
        $pr->assignMergeOrder(3);

        $this->assertSame(3, $pr->getMergeOrder());
    }

    public function testDeserializesWithoutMergeOrderField(): void
    {
        $json = '{"id":1,"title":"t","selector":{"id":1,"title":"t","labels":[]},"commits":[],"handled":false}';

        $pr = (new SfFactory())->createSfSerializer()->deserialize($json, Pr::class, 'json');

        $this->assertInstanceOf(Pr::class, $pr);
        $this->assertNull($pr->getMergeOrder());
    }
}
