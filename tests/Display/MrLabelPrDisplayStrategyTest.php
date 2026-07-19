<?php

namespace Ezdeliver\Tests\Display;

use Ezdeliver\Display\MrLabelPrDisplayStrategy;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Selector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class MrLabelPrDisplayStrategyTest extends TestCase
{
    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    public function testDisplayRendersPrAndLabelsColumns(): void
    {
        $pr = new Pr(42, 'PR title', new Selector(42, 'PR title', ['to-deliver', 'backend']), [$this->makeCommit(), $this->makeCommit('sha2')]);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('table')
            ->with(
                ['PR #ID', 'PR title', 'Labels', 'Number of commit'],
                [[42, 'PR title', 'to-deliver, backend', 2]]
            );

        (new MrLabelPrDisplayStrategy($io))->display([$pr]);
    }

    public function testDisplayRendersEmptyLabelsAsEmptyString(): void
    {
        $pr = new Pr(1, 'First', new Selector(1, 'First', []), []);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('table')
            ->with(
                ['PR #ID', 'PR title', 'Labels', 'Number of commit'],
                [[1, 'First', '', 0]]
            );

        (new MrLabelPrDisplayStrategy($io))->display([$pr]);
    }
}
