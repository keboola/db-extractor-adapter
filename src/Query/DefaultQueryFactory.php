<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Query;

use DateTimeImmutable;
use Generator;
use Keboola\DbExtractor\Adapter\Connection\DbConnection;
use Keboola\DbExtractorConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbExtractorConfig\Incremental\WindowBoundResolver;

class DefaultQueryFactory implements QueryFactory
{
    protected array $state;

    private WindowBoundResolver $resolver;

    private DateTimeImmutable $now;

    public function __construct(array $state, ?WindowBoundResolver $resolver = null, ?DateTimeImmutable $now = null)
    {
        $this->state = $state;
        $this->resolver = $resolver ?? new WindowBoundResolver();
        $this->now = $now ?? new DateTimeImmutable('now');
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

        // No window => unchanged watermark behaviour.
        if (!$exportConfig->hasIncrementalFetchingWindow()) {
            $watermark = $this->state['lastFetchedRow'] ?? null;
            if ($watermark !== null) {
                // intentionally ">=" last row should be included, it is handled by storage deduplication process
                yield sprintf('WHERE %s >= %s', $col, $connection->quote($watermark));
            }
            return;
        }

        // Window set => strict [start, end], watermark IGNORED (range re-scanned every run, deduped by PK).
        $type = $exportConfig->getIncrementalColumnType();
        $lower = $this->resolver->resolveLowerBound(
            $exportConfig->getIncrementalFetchingWindowStart(),
            $type,
            $this->now,
        );
        $upper = $this->resolver->resolveUpperBound(
            $exportConfig->getIncrementalFetchingWindowEnd(),
            $type,
            $this->now,
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
