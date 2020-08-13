<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Tests\Fixtures;

use Keboola\DbExtractor\Adapter\ExportAdapter;
use Keboola\DbExtractor\Adapter\ValueObject\ExportResult;
use Keboola\DbExtractorConfig\Configuration\ValueObject\ExportConfig;

class PassingExportAdapter implements ExportAdapter
{
    private string $name;

    private int $exportCallCount = 0;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function export(ExportConfig $exportConfig, string $csvFilePath): ExportResult
    {
        $this->exportCallCount++;
        return new ExportResult($csvFilePath, 0, null);
    }

    public function getExportCallCount(): int
    {
        return $this->exportCallCount;
    }
}
