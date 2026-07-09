<?php

namespace Ezdeliver\Tests\Model;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\Release;
use Ezdeliver\Model\Selector;
use PHPUnit\Framework\TestCase;

class ReleaseTest extends TestCase
{
    private function makeCommit(string $sha): Commit
    {
        return new Commit($sha, "message for $sha", new \DateTimeImmutable());
    }

    private function makePr(int $id, array $commits): Pr
    {
        return new Pr($id, "PR #$id", new Selector($id * 10, 'issue', []), $commits);
    }

    public function testGettersReturnConstructorValues(): void
    {
        $pr = $this->makePr(1, []);
        $release = new Release([$pr], 1, 'sha1', 'staging', 'release/v1.0');

        $this->assertSame([$pr], $release->getPrs());
        $this->assertSame(1, $release->getCurrentPrId());
        $this->assertSame('sha1', $release->getCurrentCommitSha());
        $this->assertSame('staging', $release->getEnv());
        $this->assertSame('release/v1.0', $release->getBranchName());
    }

    public function testGetConflictingPrFindsPrByCurrentPrId(): void
    {
        $commit1 = $this->makeCommit('sha1');
        $commit2 = $this->makeCommit('sha2');
        $pr1 = $this->makePr(1, [$commit1]);
        $pr2 = $this->makePr(2, [$commit2]);

        $release = new Release([$pr1, $pr2], 2, 'sha2', 'staging', 'release/v1.0');

        $this->assertSame($pr2, $release->getConflictingPr());
    }

    public function testGetConflictingCommitFindsCommitByCurrentSha(): void
    {
        $commit1 = $this->makeCommit('sha-aaa');
        $commit2 = $this->makeCommit('sha-bbb');
        $pr = $this->makePr(1, [$commit1, $commit2]);

        $release = new Release([$pr], 1, 'sha-bbb', 'staging', 'release/v1.0');

        $this->assertSame($commit2, $release->getConflictingCommit());
    }
}
