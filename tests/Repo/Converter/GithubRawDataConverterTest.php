<?php

namespace Ezdeliver\Tests\Repo\Converter;

use Ezdeliver\Repo\Converter\GithubRawDataConverter;
use PHPUnit\Framework\TestCase;

class GithubRawDataConverterTest extends TestCase
{
    public function testBuildSelectorFromRawData(): void
    {
        $raw = [
            'number' => 7,
            'title' => 'GitHub issue',
            'labels' => [
                'edges' => [
                    ['node' => ['name' => 'bug']],
                    ['node' => ['name' => 'to-deliver']],
                ],
            ],
        ];

        $selector = GithubRawDataConverter::buildSelectorFromRawData($raw);

        $this->assertSame(7, $selector->getId());
        $this->assertSame('GitHub issue', $selector->getTitle());
        $this->assertSame(['bug', 'to-deliver'], $selector->getLabels());
    }

    public function testBuildSelectorFromRawDataWithNoLabels(): void
    {
        $raw = [
            'number' => 7,
            'title' => 'GitHub issue',
            'labels' => ['edges' => []],
        ];

        $selector = GithubRawDataConverter::buildSelectorFromRawData($raw);

        $this->assertSame([], $selector->getLabels());
    }

    public function testBuildCommitFromRawData(): void
    {
        $raw = [
            'node' => [
                'commit' => [
                    'oid' => 'deadbeef',
                    'message' => 'fix: patch it',
                    'committedDate' => '2024-04-10 08:30:00',
                ],
            ],
        ];

        $commit = GithubRawDataConverter::buildCommitFromRawData($raw);

        $this->assertSame('deadbeef', $commit->getSha());
        $this->assertSame('fix: patch it', $commit->getMessage());
        $this->assertEquals(new \DateTimeImmutable('2024-04-10 08:30:00'), $commit->getDate());
    }

    public function testBuildPrFromRawData(): void
    {
        $raw = [
            'node' => [
                'number' => 99,
                'title' => 'Big PR',
                'closingIssuesReferences' => [
                    'edges' => [
                        [
                            'node' => [
                                'number' => 7,
                                'title' => 'GitHub issue',
                                'labels' => [
                                    'edges' => [['node' => ['name' => 'to-deliver']]],
                                ],
                            ],
                        ],
                    ],
                ],
                'commits' => [
                    'edges' => [
                        [
                            'node' => [
                                'commit' => [
                                    'oid' => 'sha1',
                                    'message' => 'first commit',
                                    'committedDate' => '2024-04-01 10:00:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $pr = GithubRawDataConverter::buildPrFromRawData($raw);

        $this->assertSame(99, $pr->getId());
        $this->assertSame('Big PR', $pr->getTitle());
        $this->assertSame(7, $pr->getSelector()->getId());
        $this->assertCount(1, $pr->getCommits());
        $this->assertSame('sha1', $pr->getCommits()[0]->getSha());
        $this->assertTrue($pr->getSelector()->hasLabel('to-deliver'));
    }
}
