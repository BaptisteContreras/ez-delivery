<?php

namespace Ezdeliver\Tests\Repo\Converter;

use Ezdeliver\Model\Issue;
use Ezdeliver\Repo\Converter\GitlabRawDataConverter;
use PHPUnit\Framework\TestCase;

class GitlabRawDataConverterTest extends TestCase
{
    public function testBuildIssueFromRawData(): void
    {
        $raw = [
            'iid' => 42,
            'title' => 'Fix the bug',
            'labels' => [
                'nodes' => [
                    ['title' => 'bug'],
                    ['title' => 'to-deliver'],
                ],
            ],
        ];

        $issue = GitlabRawDataConverter::buildIssueFromRawData($raw);

        $this->assertSame(42, $issue->getId());
        $this->assertSame('Fix the bug', $issue->getTitle());
        $this->assertSame(['bug', 'to-deliver'], $issue->getLabels());
    }

    public function testBuildCommitFromRawData(): void
    {
        $raw = [
            'sha' => 'abc123def456',
            'message' => 'feat: add something',
            'committedDate' => '2024-03-15 12:00:00',
        ];

        $commit = GitlabRawDataConverter::buildCommitFromRawData($raw);

        $this->assertSame('abc123def456', $commit->getSha());
        $this->assertSame('feat: add something', $commit->getMessage());
        $this->assertEquals(new \DateTimeImmutable('2024-03-15 12:00:00'), $commit->getDate());
    }

    public function testBuildPrFromRawDataReversesCommitOrderToChronological(): void
    {
        // Gitlab API returns commits newest-first; converter must reverse to oldest-first.
        $issue = new Issue(10, 'issue', []);
        $raw = [
            'iid' => 5,
            'title' => 'Add feature',
            'commits' => [
                'nodes' => [
                    ['sha' => 'sha-newest', 'message' => 'second commit', 'committedDate' => '2024-03-15 12:00:00'],
                    ['sha' => 'sha-oldest', 'message' => 'first commit',  'committedDate' => '2024-03-14 12:00:00'],
                ],
            ],
        ];

        $pr = GitlabRawDataConverter::buildPrFromRawData($raw, $issue);

        $this->assertSame(5, $pr->getId());
        $this->assertSame('Add feature', $pr->getTitle());
        $this->assertSame([], $pr->getLabels());
        $this->assertSame(10, $pr->getReference()->getId());
        $this->assertSame('issue', $pr->getReference()->getTitle());
        $this->assertCount(2, $pr->getCommits());
        $this->assertSame('sha-oldest', $pr->getCommits()[0]->getSha());
        $this->assertSame('sha-newest', $pr->getCommits()[1]->getSha());
    }

    public function testBuildPrFromRawDataWithOwnLabelsUsesMrLabelsAndNoReference(): void
    {
        $raw = [
            'iid' => 5,
            'title' => 'Add feature',
            'labels' => [
                'nodes' => [
                    ['title' => 'to-deliver:staging'],
                    ['title' => 'bug'],
                ],
            ],
            'commits' => [
                'nodes' => [
                    ['sha' => 'sha-newest', 'message' => 'second commit', 'committedDate' => '2024-03-15 12:00:00'],
                    ['sha' => 'sha-oldest', 'message' => 'first commit',  'committedDate' => '2024-03-14 12:00:00'],
                ],
            ],
        ];

        $pr = GitlabRawDataConverter::buildPrFromRawDataWithOwnLabels($raw);

        $this->assertSame(5, $pr->getId());
        $this->assertSame('Add feature', $pr->getTitle());
        $this->assertSame(['to-deliver:staging', 'bug'], $pr->getLabels());
        $this->assertNull($pr->getReference());
        $this->assertCount(2, $pr->getCommits());
        $this->assertSame('sha-oldest', $pr->getCommits()[0]->getSha());
        $this->assertSame('sha-newest', $pr->getCommits()[1]->getSha());
    }
}
