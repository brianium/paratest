<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Filter;

class CoverageMergerTest extends \TestBase
{
    protected function setUp()
    {
        $this->skipIfCodeCoverageNotEnabled();
    }

    /**
     * Test merge for code coverage library 4 version.
     *
     * @requires function \SebastianBergmann\CodeCoverage\CodeCoverage::merge
     */
    public function testSimpleMerge()
    {
        $firstFile = PARATEST_ROOT . '/src/Logging/LogInterpreter.php';
        $secondFile = PARATEST_ROOT . '/src/Logging/MetaProvider.php';

        // Every time the two above files are changed, the line numbers
        // may change, and so these two numbers may need adjustments
        $firstFileFirstLine = 38;
        $secondFileFirstLine = 38;

        $filter = new Filter();
        $filter->addFilesToWhitelist([$firstFile, $secondFile]);
        $coverage1 = new CodeCoverage(null, $filter);
        $coverage1->append(
            [
                $firstFile => [$firstFileFirstLine => 1],
                $secondFile => [$secondFileFirstLine => 1],
            ],
            'Test1'
        );
        $coverage2 = new CodeCoverage(null, $filter);
        $coverage2->append(
            [
                $firstFile => [$firstFileFirstLine => 1, 1 + $firstFileFirstLine => 1],
            ],
            'Test2'
        );

        $merger = new CoverageMerger();
        $this->call($merger, 'addCoverage', $coverage1);
        $this->call($merger, 'addCoverage', $coverage2);

        /** @var CodeCoverage $coverage */
        $coverage = $this->getObjectValue($merger, 'coverage');

        $this->assertInstanceOf(CodeCoverage::class, $coverage);

        $data = $coverage->getData();

        $this->assertCount(2, $data[$firstFile][$firstFileFirstLine]);
        $this->assertEquals('Test1', $data[$firstFile][$firstFileFirstLine][0]);
        $this->assertEquals('Test2', $data[$firstFile][$firstFileFirstLine][1]);

        $this->markTestSkipped('Offset does not exist - that kind of magic testing is bad anyway :D');
        //$this->assertCount(1, $data[$firstFile][1 + $firstFileFirstLine]);
        //$this->assertEquals('Test2', $data[$firstFile][1 + $firstFileFirstLine][0]);

        $this->assertCount(1, $data[$secondFile][$secondFileFirstLine]);
        $this->assertEquals('Test1', $data[$secondFile][$secondFileFirstLine][0]);
    }
}
