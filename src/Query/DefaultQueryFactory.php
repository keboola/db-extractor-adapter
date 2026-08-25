<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Query;

use DateTimeImmutable;
use Generator;
use Keboola\DbExtractor\Adapter\Connection\DbConnection;
use Keboola\DbExtractor\Adapter\Exception\UserException;
use Keboola\DbExtractorConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbExtractorConfig\Incremental\WindowBoundResolver;

class DefaultQueryFactory implements QueryFactory
{
    protected array $state;

    private WindowBoundResolver $resolver;

    /** Fixed-clock override for tests; null means "resolve at query-creation time" (see createWindowWhere). */
    private ?DateTimeImmutable $now;

    public function __construct(array $state, ?WindowBoundResolver $resolver = null, ?DateTimeImmutable $now = null)
    {
        $this->state = $state;
        $this->resolver = $resolver ?? new WindowBoundResolver();
        $this->now = $now;
    }

    public function create(ExportConfig $exportConfig, DbConnection $connection): string
    {
        $sql = array_merge(
            iterator_to_array($this->createSelect($exportConfig, $connection)),
            iterator_to_array($this->createFrom($exportConfig, $connection)),
            iterator_to_array($this->createWhere($exportConfig, $connection)),
            iterator_to_array($this->createOrderBy($exportConfig, $connection)),
            iterator_to_array($this->createLimit($exportConfig, $connection)),
        );
        return implode(' ', $sql);
    }

    protected function createSelect(ExportConfig $exportConfig, DbConnection $connection): Generator
    {
        if ($exportConfig->hasColumns()) {
            yield sprintf('SELECT %s', implode(', ', array_map(
                fn(string $c) => $connection->quoteIdentifier($c),
                $exportConfig->getColumns(),
            )));
        } else {
            yield 'SELECT *';
        }
    }

    protected function createFrom(ExportConfig $exportConfig, DbConnection $connection): Generator
    {
        yield sprintf(
            'FROM %s.%s',
            $connection->quoteIdentifier($exportConfig->getTable()->getSchema()),
            $connection->quoteIdentifier($exportConfig->getTable()->getName()),
        );
    }

    protected function createWhere(ExportConfig $exportConfig, DbConnection $connection): Generator
    {
        if (!$exportConfig->isIncrementalFetching()) {
            return;
        }
        $col = $connection->quoteIdentifier($exportConfig->getIncrementalFetchingColumn());

        // A fetch limit caps the ascending result to its first N rows. That is fine for plain watermark
        // chunking (each run advances past the previous max), but breaks with a window or a lookback: a
        // window keeps returning the first page of a fixed range and never advances; a lookback persists
        // an older row as the watermark and moves it backwards. Reject the combination for both.
        if ($exportConfig->hasIncrementalFetchingBounds() && $exportConfig->hasIncrementalFetchingLimit()) {
            throw new UserException(
                'Incremental fetching "incrementalFetchingLimit" cannot be combined with a window or a ' .
                'lookback: the limited result never advances through the bounded range (window) or moves ' .
                'the watermark backwards (lookback), so newer rows are never reached. Remove the limit, ' .
                'or use plain watermark mode.',
            );
        }

        // Window mode => strict [start, end], watermark IGNORED (range re-scanned every run, deduped by PK).
        if ($exportConfig->isIncrementalFetchingWindowMode()) {
            yield from $this->createWindowWhere($exportConfig, $connection, $col);
            return;
        }

        // Watermark mode (default): resume from the stored watermark, optionally lowered by a lookback
        // margin so a late-committing row (assigned below the watermark, visible only afterwards) is
        // re-scanned. On the very first run there is no watermark yet => full fetch, exactly as before.
        $watermark = $this->state['lastFetchedRow'] ?? null;
        if ($watermark === null) {
            return;
        }
        $lowerBound = (string) $watermark;
        if ($exportConfig->hasIncrementalFetchingLookback()) {
            $lowerBound = $this->resolver->resolveLookbackLowerBound(
                $lowerBound,
                (string) $exportConfig->getIncrementalFetchingLookback(),
                $exportConfig->getIncrementalColumnType(),
            );
        }
        // intentionally ">=" last row should be included, it is handled by storage deduplication process
        yield sprintf('WHERE %s >= %s', $col, $connection->quote($lowerBound));
    }

    protected function createWindowWhere(ExportConfig $exportConfig, DbConnection $connection, string $col): Generator
    {
        // Window mode ignores the watermark on purpose. With no bounds there would be no predicate at
        // all, silently turning a bounded window into a full-table scan every run. That is never what a
        // window config intends, so fail loudly instead of emitting an unfiltered query.
        if (!$exportConfig->hasIncrementalFetchingWindow()) {
            throw new UserException(
                'Incremental fetching "window" mode is enabled but neither "incrementalFetchingStart" ' .
                'nor "incrementalFetchingEnd" is set. Configure at least one bound, or switch to ' .
                '"watermark" mode.',
            );
        }

        // Resolve "now" per query, not once at construction, so a reused factory doesn't emit a stale
        // window for relative bounds ("20 minutes ago", "now"). Tests inject a fixed clock via $this->now.
        $now = $this->now ?? new DateTimeImmutable('now');

        $type = $exportConfig->getIncrementalColumnType();
        $lower = $this->resolver->resolveLowerBound(
            $exportConfig->getIncrementalFetchingWindowStart(),
            $type,
            $now,
        );
        $upper = $this->resolver->resolveUpperBound(
            $exportConfig->getIncrementalFetchingWindowEnd(),
            $type,
            $now,
        );

        $conditions = [];
        if ($lower !== null) {
            $conditions[] = sprintf('%s >= %s', $col, $connection->quote($lower));
        }
        if ($upper !== null) {
            $conditions[] = sprintf('%s <= %s', $col, $connection->quote($upper));
        }
        if ($conditions !== []) {
            yield 'WHERE ' . implode(' AND ', $conditions);
        }
    }

    protected function createOrderBy(ExportConfig $exportConfig, DbConnection $connection): Generator
    {
        if ($exportConfig->isIncrementalFetching()) {
            yield sprintf(
                'ORDER BY %s',
                $connection->quoteIdentifier($exportConfig->getIncrementalFetchingColumn()),
            );
        }
    }

    protected function createLimit(ExportConfig $exportConfig, DbConnection $connection): Generator
    {
        if ($exportConfig->hasIncrementalFetchingLimit()) {
            yield sprintf(
                'LIMIT %d',
                $exportConfig->getIncrementalFetchingLimit(),
            );
        }
    }
}
