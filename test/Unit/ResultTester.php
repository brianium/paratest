<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\TestBase;

use function file_get_contents;
use function file_put_contents;

abstract class ResultTester extends TestBase
{
    protected Suite $failureSuite;
    protected Suite $otherErrorSuite;
    protected Suite $mixedSuite;
    protected Suite $passingSuite;
    protected Suite $dataProviderSuite;
    protected Suite $errorSuite;
    protected Suite $warningSuite;
    protected Suite $skipped;

    final public function setUpTest(): void
    {
        $this->errorSuite        = $this->getSuiteWithResult('single-werror.xml', 1);
        $this->warningSuite      = $this->getSuiteWithResult('single-warning.xml', 1);
        $this->otherErrorSuite   = $this->getSuiteWithResult('single-werror2.xml', 1);
        $this->failureSuite      = $this->getSuiteWithResult('single-wfailure.xml', 3);
        $this->mixedSuite        = $this->getSuiteWithResult('mixed-results.xml', 7);
        $this->skipped           = $this->getSuiteWithResult('single-skipped.xml', 1);
        $this->passingSuite      = $this->getSuiteWithResult('single-passing.xml', 3);
        $this->dataProviderSuite = $this->getSuiteWithResult('data-provider-result.xml', 50);

        $this->setUpInterpreter();
    }

    abstract protected function setUpInterpreter(): void;

    final protected function getSuiteWithResult(string $result, int $methodCount): Suite
    {
        $functions = [];
        for ($i = 0; $i < $methodCount; ++$i) {
            $functions[] = new TestMethod((string) $i, [], false, true, TMP_DIR);
        }

        $suite = new Suite('', $functions, false, true, TMP_DIR);
        file_put_contents($suite->getTempFile(), (string) file_get_contents(FIXTURES . DS . 'results' . DS . $result));
        file_put_contents($suite->getTeamcityTempFile(), 'no data');

        return $suite;
    }
}
