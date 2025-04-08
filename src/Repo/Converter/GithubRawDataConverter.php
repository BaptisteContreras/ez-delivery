<?php

namespace Ezdeliver\Repo\Converter;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;

final class GithubRawDataConverter
{
    public static function buildPrFromRawData(array $rawData): Pr
    {
        $prData = $rawData['node'];

        return new Pr(
            $prData['number'],
            $prData['title'],
            self::buildIssueFromRawData($prData['closingIssuesReferences']['edges'][0]['node']),
            array_map(fn (array $commitData) => self::buildCommitFromRawData($commitData), $prData['commits']['edges']),
        );
    }

    public static function buildIssueFromRawData(array $rawData): Issue
    {
        $labelsData = !empty($rawData['labels']['edges']) ? $rawData['labels']['edges'] : [];

        return new Issue(
            $rawData['number'],
            $rawData['title'],
            array_map(fn (array $labelData) => $labelData['node']['name'], $labelsData)
        );
    }

    public static function buildCommitFromRawData(array $rawData): Commit
    {
        $commitData = $rawData['node']['commit'];

        return new Commit(
            $commitData['oid'],
            $commitData['message'],
            new \DateTimeImmutable($commitData['committedDate']),
        );
    }
}
