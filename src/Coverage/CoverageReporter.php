<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use PHPUnit\TextUI\XmlConfiguration\CodeCoverage\CodeCoverage as CodeCoverageConfiguration;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Cobertura;
use SebastianBergmann\CodeCoverage\Report\Crap4j;
use SebastianBergmann\CodeCoverage\Report\Html;
use SebastianBergmann\CodeCoverage\Report\PHP;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Xml\Facade as XmlReport;
use SebastianBergmann\CodeCoverage\Version;

/**
 * @internal
 */
final class CoverageReporter
{
    public function __construct(
        private CodeCoverage $coverage,
        private ?CodeCoverageConfiguration $codeCoverageConfiguration
    ) {
    }

    /**
     * Generate clover coverage report.
     *
     * @param string $target Report filename
     */
    public function clover(string $target): void
    {
        $clover = new Clover();
        $clover->process($this->coverage, $target);
    }

    public function cobertura(string $target): void
    {
        $clover = new Cobertura();
        $clover->process($this->coverage, $target);
    }

    /**
     * Generate Crap4J XML coverage report.
     *
     * @param string $target Report filename
     */
    public function crap4j(string $target): void
    {
        $xml = new Crap4j();
        if ($this->codeCoverageConfiguration !== null && $this->codeCoverageConfiguration->hasCrap4j()) {
            $xml = new Crap4j($this->codeCoverageConfiguration->crap4j()->threshold());
        }

        $xml->process($this->coverage, $target);
    }

    /**
     * Generate html coverage report.
     *
     * @param string $target Report filename
     */
    public function html(string $target): void
    {
        $html = new Html\Facade();
        if ($this->codeCoverageConfiguration !== null && $this->codeCoverageConfiguration->hasHtml()) {
            $html = new Html\Facade(
                $this->codeCoverageConfiguration->html()->lowUpperBound(),
                $this->codeCoverageConfiguration->html()->highLowerBound()
            );
        }

        $html->process($this->coverage, $target);
    }

    /**
     * Generate php coverage report.
     *
     * @param string $target Report filename
     */
    public function php(string $target): void
    {
        $php = new PHP();
        $php->process($this->coverage, $target);
    }

    /**
     * Generate text coverage report.
     */
    public function text(): string
    {
        $text = new Text();

        return $text->process($this->coverage);
    }

    /**
     * Generate PHPUnit XML coverage report.
     *
     * @param string $target Report filename
     */
    public function xml(string $target): void
    {
        $xml = new XmlReport(Version::id());
        $xml->process($this->coverage, $target);
    }
}
