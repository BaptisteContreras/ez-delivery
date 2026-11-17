<?php

namespace Ezdeliver\Core\Repo;

use Ezdeliver\Core\Model\Pr;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Orders PRs for delivery from a "<prefix>N" selector label:
 * labelled PRs first by N, then unlabelled ones, ties broken by PR id.
 */
final class PrMergeOrderSorter
{
    private const int MAX_ORDER_DIGITS = 9;

    public function __construct(
        private readonly SymfonyStyle $io,
    ) {
    }

    /**
     * @param array<Pr> $prs
     *
     * @return list<Pr>
     */
    public function sort(array $prs, string $labelPrefix): array
    {
        // An empty prefix would match every label: ignore order labels rather than misorder.
        if ('' === $labelPrefix) {
            $this->io->warning('Merge order label prefix is empty in the project config, merge order labels are ignored');
        }

        foreach ($prs as $pr) {
            $pr->assignMergeOrder('' === $labelPrefix ? null : $this->resolveMergeOrder($pr, $labelPrefix));
        }

        $prs = array_values($prs);

        usort($prs, fn (Pr $a, Pr $b) => $this->sortKey($a) <=> $this->sortKey($b));

        return $prs;
    }

    private function resolveMergeOrder(Pr $pr, string $labelPrefix): ?int
    {
        $orders = [];

        foreach ($pr->getSelectorLabels() as $label) {
            if (!str_starts_with($label, $labelPrefix)) {
                continue;
            }

            $value = substr($label, strlen($labelPrefix));

            if (!ctype_digit($value) || strlen($value) > self::MAX_ORDER_DIGITS) {
                $this->io->warning(sprintf('PR #%d: ignoring invalid merge order label "%s"', $pr->getId(), $label));

                continue;
            }

            $orders[] = (int) $value;
        }

        if ([] === $orders) {
            return null;
        }

        $order = min($orders);

        if (count($orders) > 1) {
            $this->io->warning(sprintf('PR #%d: several merge order labels, using %d', $pr->getId(), $order));
        }

        return $order;
    }

    /**
     * @return array{int, int, int}
     */
    private function sortKey(Pr $pr): array
    {
        $order = $pr->getMergeOrder();

        return [null === $order ? 1 : 0, $order ?? 0, $pr->getId()];
    }
}
