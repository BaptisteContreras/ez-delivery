<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;
use Ezdeliver\Repo\GitlabIssueLabelsUpdateStrategy;
use Ezdeliver\Repo\GitlabMrLabelsUpdateStrategy;
use PHPUnit\Framework\TestCase;

class LabelsUpdateStrategyTest extends TestCase
{
    private function makePr(int $id, ?PrReference $reference): Pr
    {
        $commit = new Commit("sha$id", 'message', new \DateTimeImmutable());

        return new Pr($id, "PR $id", [], $reference, [$commit]);
    }

    public function testGitlabIssueLabelsUpdateStrategyTargetsTheLinkedIssue(): void
    {
        $pr = $this->makePr(7, new PrReference(10, 'Issue title', []));

        $update = (new GitlabIssueLabelsUpdateStrategy())->buildUpdate($pr, ['delivered:staging']);

        $this->assertSame(10, $update->getTargetId());
        $this->assertSame('Issue title', $update->getTargetTitle());
        $this->assertSame(['delivered:staging'], $update->getLabels());
    }

    public function testGitlabIssueLabelsUpdateStrategyFallsBackToPrWhenNoReference(): void
    {
        $pr = $this->makePr(7, null);

        $update = (new GitlabIssueLabelsUpdateStrategy())->buildUpdate($pr, ['delivered:staging']);

        $this->assertSame(7, $update->getTargetId());
        $this->assertSame('PR 7', $update->getTargetTitle());
    }

    public function testGitlabMrLabelsUpdateStrategyTargetsThePrItself(): void
    {
        $pr = $this->makePr(7, new PrReference(10, 'Issue title', []));

        $update = (new GitlabMrLabelsUpdateStrategy())->buildUpdate($pr, ['delivered:staging']);

        $this->assertSame(7, $update->getTargetId());
        $this->assertSame('PR 7', $update->getTargetTitle());
        $this->assertSame(['delivered:staging'], $update->getLabels());
    }
}
