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
use Symfony\Component\Console\Style\SymfonyStyle;

class RemoteRepoTest extends TestCase
{
    private function makeRemoteRepo(array $drivers, ?SymfonyStyle $io = null): RemoteRepo
    {
        return new RemoteRepo($drivers, $io ?? $this->createMock(SymfonyStyle::class));
    }

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

        $result = $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertContains($pr, $result);
    }

    public function testGetPrsToDeliverIncludesPrsWithAlreadyDeliveredLabel(): void
    {
        $pr = $this->makePr(1, ['delivered:staging']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertContains($pr, $result);
    }

    public function testGetPrsToDeliverExcludesPrsWithoutMatchingLabel(): void
    {
        $pr = $this->makePr(1, ['bug']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertEmpty($result);
    }

    public function testGetPrsToDeliverLogsIncludedAndExcludedPrsWhenVerbose(): void
    {
        $includedPr = $this->makePr(1, ['to-deliver:staging']);
        $excludedPr = $this->makePr(2, ['bug']);
        $driver = $this->makeDriverThatSupports([$includedPr, $excludedPr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);

        $comments = [];
        $io->expects($this->exactly(2))
            ->method('comment')
            ->willReturnCallback(function ($line) use (&$comments) {
                $comments[] = $line;
            });

        $this->makeRemoteRepo([$driver], $io)->getPrsToDeliver($repoConfig, $this->makeEnv());

        $this->assertStringContainsString('INCLUDED', $comments[0]);
        $this->assertStringContainsString('PR #1', $comments[0]);
        $this->assertStringContainsString('issue #10', $comments[0]);
        $this->assertStringContainsString('excluded', $comments[1]);
        $this->assertStringContainsString('PR #2', $comments[1]);
    }

    public function testGetPrsToDeliverDoesNotLogWhenNotVerbose(): void
    {
        $pr = $this->makePr(1, ['to-deliver:staging']);
        $driver = $this->makeDriverThatSupports([$pr]);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(false);
        $io->expects($this->never())->method('comment');

        $this->makeRemoteRepo([$driver], $io)->getPrsToDeliver($repoConfig, $this->makeEnv());
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

        $this->makeRemoteRepo([$driver])->updateLabels($repoConfig, [$pr], $this->makeEnv());

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

        $this->makeRemoteRepo([$driver])->updateLabels($repoConfig, [$pr], $this->makeEnv());
    }

    public function testSupportLabelsUpdateDelegatesToDriver(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(true);
        $driver->method('supportLabelsUpdate')->willReturn(true);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $result = $this->makeRemoteRepo([$driver])->supportLabelsUpdate($repoConfig);

        $this->assertTrue($result);
    }

    public function testSelectDriverThrowsWhenNoDriverSupports(): void
    {
        $driver = $this->createMock(RemoteRepoDriver::class);
        $driver->method('support')->willReturn(false);
        $repoConfig = $this->createMock(ProjectRepoConfig::class);

        $this->expectException(DriverNotFoundException::class);

        $this->makeRemoteRepo([$driver])->getPrsToDeliver($repoConfig, $this->makeEnv());
    }
}
