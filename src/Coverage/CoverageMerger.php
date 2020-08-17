<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use RuntimeException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\ProcessedCodeCoverageData;
use SebastianBergmann\Environment\Runtime;

use function array_map;
use function array_slice;
use function filesize;
use function is_file;
use function unlink;

final class CoverageMerger
{
    /** @var CodeCoverage|null */
    private $coverage;
    /** @var int */
    private $testLimit;

    public function __construct(int $testLimit)
    {
        $this->testLimit = $testLimit;
    }

    private function addCoverage(CodeCoverage $coverage): void
    {
        if ($this->coverage === null) {
            $this->coverage = $coverage;
        } else {
            $this->coverage->merge($coverage);
        }

        $this->limitCoverageTests($this->coverage);
    }

    /**
     * Adds the coverage contained in $coverageFile and deletes the file afterwards.
     *
     * @param string $coverageFile Code coverage file
     *
     * @throws RuntimeException When coverage file is empty.
     */
    public function addCoverageFromFile(string $coverageFile): void
    {
        if (! is_file($coverageFile) || filesize($coverageFile) === 0) {
            $extra = 'This means a PHPUnit process has crashed.';
            if (! (new Runtime())->canCollectCodeCoverage()) {
                $extra = 'No coverage driver found! Enable one of Xdebug, PHPDBG or PCOV for coverage.';
            }

            throw new RuntimeException("Coverage file {$coverageFile} is empty. " . $extra);
        }

        /** @psalm-suppress UnresolvableInclude **/
        $this->addCoverage(include $coverageFile);

        unlink($coverageFile);
    }

    public function getCodeCoverageObject(): ?CodeCoverage
    {
        return $this->coverage;
    }

    private function limitCoverageTests(CodeCoverage $coverage): void
    {
        if ($this->testLimit === 0) {
            return;
        }

        $testLimit     = $this->testLimit;
        $data          = $coverage->getData(true);
        $newData       = array_map(
            static function (array $lines) use ($testLimit): array {
                return array_map(static function (array $value) use ($testLimit): array {
                    return array_slice($value, 0, $testLimit);
                }, $lines);
            },
            $data->lineCoverage(),
        );
        $processedData = new ProcessedCodeCoverageData();
        $processedData->setLineCoverage($newData);

        $coverage->setData($processedData);
    }
}
