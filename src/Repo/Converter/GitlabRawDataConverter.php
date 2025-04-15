<?php

namespace Ezdeliver\Repo\Converter;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;

final class GitlabRawDataConverter
{
    public static function buildPrFromRawData(array $rawData, Issue $issue): Pr
    {
        return new Pr(
            $rawData['iid'],
            $rawData['title'],
            $issue,
            array_reverse(array_map(fn (array $commitData) => self::buildCommitFromRawData($commitData), $rawData['commits']['nodes']))
        );
    }

    public static function buildIssueFromRawData(array $rawData): Issue
    {
        return new Issue(
            $rawData['iid'],
            $rawData['title'],
            array_map(fn (array $labelData) => $labelData['title'], $rawData['labels']['nodes'])
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
}
