<?php

namespace Ezdeliver\Tests\Repo;

use Ezdeliver\Config\Model\ProjectEnvConfig;
use Ezdeliver\Config\Model\ProjectRepoConfig;
use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;
use Ezdeliver\Repo\DriverNotFoundException;
use Ezdeliver\Repo\IssueLabelsUpdate;
use Ezdeliver\Repo\RemoteRepo;
use Ezdeliver\Repo\RemoteRepoDriver;
use PHPUnit\Framework\TestCase;

class RemoteRepoTest extends TestCase
{
    private function makeEnv(): ProjectEnvConfig
    {
        return new ProjectEnvConfig('staging', 'delivered:staging', 'to-deliver:staging');
    }

    private function makePr(int $id, array $issueLabels): Pr
    {
        $issue = new Issue($id * 10, "Issue $id", $issueLabels);
        $commit = new Commit("sha$id", 'message', new \DateTimeImmutable());

        return new Pr($id, "PR $id", $issue, [$commit]);
    }

    private function makeDriverThatSupports(array $prs = []): RemoteRepoDriver
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $driver->method('getPrsWithLinkedIssue')->willReturn($prs);

        return $driver;
    }

    public function testGetPrsToDeliverIncludesPrsWithToDeliverLabel(): void
    {
        $pr = $this->makePr(1, ['to-deliver:staging']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = (new RemoteRepo([$driver]))->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertContains($pr, $result);
    }

    public function testGetPrsToDeliverIncludesPrsWithAlreadyDeliveredLabel(): void
    {
        $pr = $this->makePr(1, ['delivered:staging']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = (new RemoteRepo([$driver]))->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertContains($pr, $result);
    }

    public function testGetPrsToDeliverExcludesPrsWithoutMatchingLabel(): void
    {
        $pr = $this->makePr(1, ['bug']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = (new RemoteRepo([$driver]))->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertEmpty($result);
    }

    public function testUpdateLabelsSwapsToDeliverLabelForAlreadyDelivered(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $issue = new Issue(10, 'Issue title', ['to-deliver:staging', 'bug']);
        $pr = new Pr(1, 'PR title', $issue, []);

        $capturedUpdates = null;
        $driver->expects($this->once())
            ->method('updateLabels')
            ->willReturnCallback(function ($config, array $updates) use (&$capturedUpdates) {
                $capturedUpdates = $updates;
            });

        (new RemoteRepo([$driver]))->updateLabels($repoConfig, [$pr], $this->makeEnv());

        $this->assertCount(1, $capturedUpdates);
        /** @var IssueLabelsUpdate $update */
        $update = array_values($capturedUpdates)[0];
        $this->assertSame(10, $update->getIssueId());
        $this->assertSame('Issue title', $update->getIssueTitle());
        $this->assertContains('delivered:staging', $update->getLabels());
        $this->assertNotContains('to-deliver:staging', $update->getLabels());
        $this->assertContains('bug', $update->getLabels());
    }

    public function testUpdateLabelsSkipsIssuesWithoutToDeliverLabel(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $issue = new Issue(10, 'Issue title', ['delivered:staging']);
        $pr = new Pr(1, 'PR title', $issue, []);

        $driver->expects($this->once())
            ->method('updateLabels')
            ->with($this->anything(), $this->isEmpty());

        (new RemoteRepo([$driver]))->updateLabels($repoConfig, [$pr], $this->makeEnv());
    }

    public function testSupportLabelsUpdateDelegatesToDriver(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $driver->method('supportLabelsUpdate')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = (new RemoteRepo([$driver]))->supportLabelsUpdate($repoConfig);

        $this->assertTrue($result);
    }

    public function testSelectDriverThrowsWhenNoDriverSupports(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(false);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $this->expectException(DriverNotFoundException::class);

        (new RemoteRepo([$driver]))->getPrsToDeliver($repoConfig, $this->makeEnv());
    }
}
