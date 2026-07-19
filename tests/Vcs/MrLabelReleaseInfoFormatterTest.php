<?php

namespace Ezdeliver\Tests\Vcs;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Selector;
use Ezdeliver\Vcs\MrLabelReleaseInfoFormatter;
use PHPUnit\Framework\TestCase;

class MrLabelReleaseInfoFormatterTest extends TestCase
{
    private function makeCommit(string $sha = 'sha1'): Commit
    {
        return new Commit($sha, 'message', new \DateTimeImmutable());
    }

    public function testFormatReleaseMessageUsesPrOwnTitleNotSelectorTitle(): void
    {
        $pr = new Pr(42, 'Fix login bug', new Selector(42, 'Fix login bug', ['to-deliver']), [$this->makeCommit('sha-abc')]);

        $message = (new MrLabelReleaseInfoFormatter())->formatReleaseMessage([$pr]);

        $expected = sprintf('AUTO RELEASE %s', PHP_EOL)
            .sprintf('Number of PRs delivered : %s %s', 1, PHP_EOL)
            .sprintf('PR #ID, PR title, Number of commit, [Commits] %s%s%s', PHP_EOL, PHP_EOL, PHP_EOL)
            .sprintf('-   #!%s, "%s", %s, [%s] %s', 42, 'Fix login bug', 1, '"sha-abc"', PHP_EOL);

        $this->assertSame($expected, $message);
    }

    public function testFormatReleaseMessageContainsNoIssueWording(): void
    {
        $pr = new Pr(1, 'First', new Selector(1, 'First', []), []);

        $message = (new MrLabelReleaseInfoFormatter())->formatReleaseMessage([$pr]);

        $this->assertStringNotContainsString('Issue', $message);
    }

    public function testFormatReleaseMessageConcatenatesOneLinePerPr(): void
    {
        $prOne = new Pr(1, 'First', new Selector(1, 'First', []), [$this->makeCommit('sha1')]);
        $prTwo = new Pr(2, 'Second', new Selector(2, 'Second', []), []);

        $message = (new MrLabelReleaseInfoFormatter())->formatReleaseMessage([$prOne, $prTwo]);

        $this->assertStringContainsString('Number of PRs delivered : 2', $message);
        $this->assertStringContainsString(sprintf('-   #!%s, "%s", %s, [%s] %s', 1, 'First', 1, '"sha1"', PHP_EOL), $message);
        $this->assertStringContainsString(sprintf('-   #!%s, "%s", %s, [%s] %s', 2, 'Second', 0, '', PHP_EOL), $message);
    }
}
