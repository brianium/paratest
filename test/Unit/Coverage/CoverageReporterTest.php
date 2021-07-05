<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Coverage;

use ParaTest\Coverage\CoverageReporter;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use PHPUnit\Util\Xml;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\RawCodeCoverageData;

use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Coverage\CoverageReporter
 */
final class CoverageReporterTest extends TestBase
{
    private CoverageReporter $coverageReporter;

    protected function setUpTest(): void
    {
        static::skipIfCodeCoverageNotEnabled();

        $filter = new Filter();
        $filter->includeFile(__FILE__);
        $codeCoverage = new CodeCoverage((new Selector())->forLineCoverage($filter), $filter);
        $codeCoverage->append(RawCodeCoverageData::fromXdebugWithoutPathCoverage([
            __FILE__ => [__LINE__ => 1],
        ]), uniqid());

        $configuration = (new Loader())->load($this->fixture('phpunit-fully-configured.xml'));

        $this->coverageReporter = new CoverageReporter($codeCoverage, $configuration->codeCoverage());
    }

    public function testGenerateClover(): void
    {
        $target = TMP_DIR . DS . 'clover';

        $this->coverageReporter->clover($target);

        static::assertFileExists($target);

        $reportXml = (new Xml\Loader())->loadFile($target);
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect clover report xml was generated');
    }

    public function testGenerateCobertura(): void
    {
        $target = TMP_DIR . DS . 'cobertura';

        $this->coverageReporter->cobertura($target);

        static::assertFileExists($target);

        $reportXml = (new Xml\Loader())->loadFile($target);
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect Cobertura report xml was generated');
    }

    public function testGenerateCrap4j(): void
    {
        $target = TMP_DIR . DS . 'crap4j';

        $this->coverageReporter->crap4j($target);

        static::assertFileExists($target);

        $reportXml = (new Xml\Loader())->loadFile($target);
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect crap4j report xml was generated');
        $documentElement = $reportXml->documentElement;
        static::assertNotNull($documentElement);
        static::assertEquals('crap_result', $documentElement->tagName);
    }

    public function testGenerateHtml(): void
    {
        $target = TMP_DIR . DS . 'html';

        $this->coverageReporter->html($target);

        static::assertFileExists($target);
        static::assertFileExists($target . DS . 'index.html', 'Index html file was not generated');
    }

    public function testGeneratePhp(): void
    {
        $target = TMP_DIR . DS . 'php';

        $this->coverageReporter->php($target);

        static::assertFileExists($target);
    }

    public function testGenerateText(): void
    {
        $output = $this->coverageReporter->text();

        static::assertStringContainsString('Code Coverage Report:', $output);
    }

    public function testGenerateXml(): void
    {
        $target = TMP_DIR . DS . 'xml';

        $this->coverageReporter->xml($target);

        static::assertFileExists($target);
        static::assertFileExists($target . DS . 'index.xml', 'Index xml file was not generated');

        $reportXml = (new Xml\Loader())->loadFile($target . DS . 'index.xml');
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect xml was generated');
    }
}
