<?php

namespace Ezdeliver\Repo\Converter;

use Ezdeliver\Model\Commit;
use Ezdeliver\Model\Issue;
use Ezdeliver\Model\Pr;
use Ezdeliver\Model\PrReference;

final class GithubRawDataConverter
{
    public static function buildPrFromRawData(array $rawData): Pr
    {
        $prData = $rawData['node'];
        $issue = self::buildIssueFromRawData($prData['closingIssuesReferences']['edges'][0]['node']);

        return new Pr(
            $prData['number'],
            $prData['title'],
            self::extractLabels($prData['labels']),
            new PrReference($issue->getId(), $issue->getTitle(), $issue->getLabels()),
            array_map(fn (array $commitData) => self::buildCommitFromRawData($commitData), $prData['commits']['edges']),
        );
    }

    public static function buildIssueFromRawData(array $rawData): Issue
    {
        return new Issue(
            $rawData['number'],
            $rawData['title'],
            self::extractLabels($rawData['labels'])
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

    /**
     * @return array<string>
     */
    private static function extractLabels(array $labelsData): array
    {
        $labelEdges = !empty($labelsData['edges']) ? $labelsData['edges'] : [];

        return array_map(fn (array $labelData) => $labelData['node']['name'], $labelEdges);
    }
}
