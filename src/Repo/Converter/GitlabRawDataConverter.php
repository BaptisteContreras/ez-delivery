<?php

namespace Ezdeliver\Repo\Converter;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;

final class GitlabRawDataConverter
{
    public static function buildPrFromRawData(array $rawData, Issue $issue): Pr
    {
        return new Pr(
            $rawData['iid'],
            $rawData['title'],
            self::extractLabels($rawData['labels']['nodes']),
            new PrReference($issue->getId(), $issue->getTitle(), $issue->getLabels()),
            array_reverse(array_map(fn (array $commitData) => self::buildCommitFromRawData($commitData), $rawData['commits']['nodes']))
        );
    }

    public static function buildPrFromRawDataWithOwnLabels(array $rawData): Pr
    {
        return new Pr(
            $rawData['iid'],
            $rawData['title'],
            self::extractLabels($rawData['labels']['nodes']),
            null,
            array_reverse(array_map(fn (array $commitData) => self::buildCommitFromRawData($commitData), $rawData['commits']['nodes']))
        );
    }

    public static function buildIssueFromRawData(array $rawData): Issue
    {
        return new Issue(
            $rawData['iid'],
            $rawData['title'],
            self::extractLabels($rawData['labels']['nodes'])
        );
    }

    public static function buildCommitFromRawData(array $rawData): Commit
    {
        return new Commit(
            $rawData['sha'],
            $rawData['message'],
            new \DateTimeImmutable($rawData['committedDate']),
        );
    }

    /**
     * @param array<array{title: string}> $labelNodes
     *
     * @return array<string>
     */
    private static function extractLabels(array $labelNodes): array
    {
        return array_map(fn (array $labelData) => $labelData['title'], $labelNodes);
    }
}
