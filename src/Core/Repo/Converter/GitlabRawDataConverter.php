<?php

namespace Ezdeliver\Core\Repo\Converter;

use Ezdeliver\Core\Model\Commit;
use Ezdeliver\Core\Model\Pr;
use Ezdeliver\Core\Model\Selector;

final class GitlabRawDataConverter
{
    public static function buildPrFromRawData(array $rawData, Selector $selector): Pr
    {
        return new Pr(
            $rawData['iid'],
            $rawData['title'],
            $selector,
            array_reverse(array_map(fn (array $commitData) => self::buildCommitFromRawData($commitData), $rawData['commits']['nodes']))
        );
    }

    public static function buildSelectorFromRawData(array $rawData): Selector
    {
        return new Selector(
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
