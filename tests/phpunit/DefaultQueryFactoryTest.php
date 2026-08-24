<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Tests;

use DateTimeImmutable;
use Keboola\DbExtractor\Adapter\Exception\UserException;
use Keboola\DbExtractor\Adapter\Query\DefaultQueryFactory;
use Keboola\DbExtractor\Adapter\Tests\Traits\PdoCreateConnectionTrait;
use Keboola\DbExtractorConfig\Incremental\WindowBoundResolver;
use PHPUnit\Framework\Assert;

class DefaultQueryFactoryTest extends BaseTest
{
    use PdoCreateConnectionTrait;

    /**
     * @dataProvider getQueryFactoryInputs
     */
    public function testQueryFactory(string $expectedQuery, array $exportConfig, array $state = []): void
    {
        $queryFactory = new DefaultQueryFactory($state);
        $query = $queryFactory->create($this->createExportConfig($exportConfig), $this->createPdoConnection());
        Assert::assertSame($expectedQuery, $query);
    }

    public function getQueryFactoryInputs(): array
    {
        return [
            'minimal' => [
                'SELECT * FROM `bar`.`foo`',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                ],
            ],
            'columns' => [
                'SELECT `col1`, `col2` FROM `bar`.`foo`',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                    'columns' => ['col1', 'col2'],
                ],
            ],
            'incrementalFetchingNoState' => [
                'SELECT * FROM `bar`.`foo` ORDER BY `col2`',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                    'incrementalFetchingColumn' => 'col2',
                ],
            ],
            'incrementalFetchingNoStateColumns' => [
                'SELECT `col1`, `col2` FROM `bar`.`foo` ORDER BY `col2`',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                    'columns' => ['col1', 'col2'],
                    'incrementalFetchingColumn' => 'col2',
                ],
            ],
            'incrementalFetchingState' => [
                'SELECT * FROM `bar`.`foo` WHERE `col2` >= \'123\' ORDER BY `col2`',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                    'incrementalFetchingColumn' => 'col2',
                ],
                [
                    'lastFetchedRow' => '123',
                ],
            ],
            'incrementalFetchingStateColumns' => [
                'SELECT `col1`, `col2` FROM `bar`.`foo` WHERE `col2` >= \'123\' ORDER BY `col2`',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                    'columns' => ['col1', 'col2'],
                    'incrementalFetchingColumn' => 'col2',
                ],
                [
                    'lastFetchedRow' => '123',
                ],
            ],
            'incrementalFetchingStateLimit' => [
                'SELECT * FROM `bar`.`foo` WHERE `col2` >= \'123\' ORDER BY `col2` LIMIT 321',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                    'incrementalFetchingColumn' => 'col2',
                    'incrementalFetchingLimit' => 321,
                ],
                [
                    'lastFetchedRow' => '123',
                ],
            ],
            'incrementalFetchingStateLimitColumns' => [
                'SELECT `col1`, `col2` FROM `bar`.`foo` WHERE `col2` >= \'123\' ORDER BY `col2` LIMIT 321',
                [
                    'table' => ['tableName' => 'foo', 'schema' => 'bar'],
                    'columns' => ['col1', 'col2'],
                    'incrementalFetchingColumn' => 'col2',
                    'incrementalFetchingLimit' => 321,
                ],
                [
                    'lastFetchedRow' => '123',
                ],
            ],
        ];
    }

    public function testQueryFactoryWindowRelative(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        // watermark present but MUST be ignored on the window path
        $factory = new DefaultQueryFactory(
            ['lastFetchedRow' => '2026-08-11 11:00:00'],
            new WindowBoundResolver(),
            $now,
        );
        $exportConfig = $this->createExportConfig([
            'table' => ['tableName' => 'foo', 'schema' => 'bar'],
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingMode' => 'window',
            'incrementalFetchingStart' => '20 minutes ago',
            'incrementalFetchingEnd' => 'now',
        ])->withIncrementalColumnType('TIMESTAMP');

        $query = $factory->create($exportConfig, $this->createPdoConnection());
        Assert::assertSame(
            'SELECT * FROM `bar`.`foo` WHERE `ts` >= \'2026-08-11 11:40:00\' ' .
            'AND `ts` <= \'2026-08-11 12:00:00\' ORDER BY `ts`',
            $query,
        );
    }

    public function testQueryFactoryWindowStartOnlyAbsolute(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $factory = new DefaultQueryFactory([], new WindowBoundResolver(), $now);
        $exportConfig = $this->createExportConfig([
            'table' => ['tableName' => 'foo', 'schema' => 'bar'],
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingMode' => 'window',
            'incrementalFetchingStart' => '2020-01-01',
        ])->withIncrementalColumnType('TIMESTAMP');

        $query = $factory->create($exportConfig, $this->createPdoConnection());
        Assert::assertSame(
            'SELECT * FROM `bar`.`foo` WHERE `ts` >= \'2020-01-01 00:00:00\' ORDER BY `ts`',
            $query,
        );
    }

    public function testQueryFactoryWindowNumericAbsolute(): void
    {
        $now = new DateTimeImmutable('2026-08-11 12:00:00');
        $factory = new DefaultQueryFactory([], new WindowBoundResolver(), $now);
        $exportConfig = $this->createExportConfig([
            'table' => ['tableName' => 'foo', 'schema' => 'bar'],
            'incrementalFetchingColumn' => 'id',
            'incrementalFetchingMode' => 'window',
            'incrementalFetchingStart' => '1000',
            'incrementalFetchingEnd' => '2000',
        ])->withIncrementalColumnType('INTEGER');

        $query = $factory->create($exportConfig, $this->createPdoConnection());
        Assert::assertSame(
            'SELECT * FROM `bar`.`foo` WHERE `id` >= \'1000\' AND `id` <= \'2000\' ORDER BY `id`',
            $query,
        );
    }

    public function testQueryFactoryWatermarkLookbackTimestamp(): void
    {
        // Watermark mode (default) with a lookback: the WHERE lower bound is the stored watermark
        // shifted back by the lookback duration; "now" is irrelevant on this path.
        $factory = new DefaultQueryFactory(
            ['lastFetchedRow' => '2026-08-11 12:00:00'],
            new WindowBoundResolver(),
            new DateTimeImmutable('2030-01-01 00:00:00'),
        );
        $exportConfig = $this->createExportConfig([
            'table' => ['tableName' => 'foo', 'schema' => 'bar'],
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingLookback' => '20 minutes',
        ])->withIncrementalColumnType('TIMESTAMP');

        $query = $factory->create($exportConfig, $this->createPdoConnection());
        Assert::assertSame(
            'SELECT * FROM `bar`.`foo` WHERE `ts` >= \'2026-08-11 11:40:00\' ORDER BY `ts`',
            $query,
        );
    }

    public function testQueryFactoryWatermarkLookbackNumeric(): void
    {
        $factory = new DefaultQueryFactory(
            ['lastFetchedRow' => '10000'],
            new WindowBoundResolver(),
            new DateTimeImmutable('2026-08-11 12:00:00'),
        );
        $exportConfig = $this->createExportConfig([
            'table' => ['tableName' => 'foo', 'schema' => 'bar'],
            'incrementalFetchingColumn' => 'id',
            'incrementalFetchingLookback' => '10',
        ])->withIncrementalColumnType('INTEGER');

        $query = $factory->create($exportConfig, $this->createPdoConnection());
        Assert::assertSame(
            'SELECT * FROM `bar`.`foo` WHERE `id` >= \'9990\' ORDER BY `id`',
            $query,
        );
    }

    public function testQueryFactoryWatermarkLookbackNoStateIsFullFetch(): void
    {
        // First run (no watermark yet): a lookback cannot lower anything, so no WHERE is emitted.
        $factory = new DefaultQueryFactory([], new WindowBoundResolver(), new DateTimeImmutable('2026-08-11 12:00:00'));
        $exportConfig = $this->createExportConfig([
            'table' => ['tableName' => 'foo', 'schema' => 'bar'],
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingLookback' => '20 minutes',
        ])->withIncrementalColumnType('TIMESTAMP');

        $query = $factory->create($exportConfig, $this->createPdoConnection());
        Assert::assertSame('SELECT * FROM `bar`.`foo` ORDER BY `ts`', $query);
    }

    public function testQueryFactoryWindowModeWithoutBoundsFails(): void
    {
        // Window mode ignores the watermark; with no start/end it would degrade to a full-table scan.
        // That is never intended, so the factory must fail loudly instead of emitting an unfiltered query.
        $factory = new DefaultQueryFactory(
            ['lastFetchedRow' => '2026-08-11 11:00:00'],
            new WindowBoundResolver(),
            new DateTimeImmutable('2026-08-11 12:00:00'),
        );
        $exportConfig = $this->createExportConfig([
            'table' => ['tableName' => 'foo', 'schema' => 'bar'],
            'incrementalFetchingColumn' => 'ts',
            'incrementalFetchingMode' => 'window',
        ])->withIncrementalColumnType('TIMESTAMP');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Incremental fetching "window" mode is enabled but neither');
        $factory->create($exportConfig, $this->createPdoConnection());
    }
}
