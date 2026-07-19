<?php

namespace Ezdeliver\Tests\Display;

use Ezdeliver\Display\LinkedIssuePrDisplayStrategy;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Selector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class LinkedIssuePrDisplayStrategyTest extends TestCase
{
    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    public function testDisplayRendersPrAndIssueColumns(): void
    {
        $pr = new Pr(42, 'PR title', new Selector(7, 'Issue title', ['to-deliver']), [$this->makeCommit(), $this->makeCommit('sha2')]);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('table')
            ->with(
                ['PR #ID', 'PR title', 'issue #ID', 'issue title', 'Number of commit'],
                [[42, 'PR title', 7, 'Issue title', 2]]
            );

        (new LinkedIssuePrDisplayStrategy($io))->display([$pr]);
    }

    public function testDisplayRendersOneRowPerPr(): void
    {
        $prOne = new Pr(1, 'First', new Selector(10, 'Issue A', []), [$this->makeCommit()]);
        $prTwo = new Pr(2, 'Second', new Selector(20, 'Issue B', []), []);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('table')
            ->with(
                ['PR #ID', 'PR title', 'issue #ID', 'issue title', 'Number of commit'],
                [
                    [1, 'First', 10, 'Issue A', 1],
                    [2, 'Second', 20, 'Issue B', 0],
                ]
            );

        (new LinkedIssuePrDisplayStrategy($io))->display([$prOne, $prTwo]);
    }
}
