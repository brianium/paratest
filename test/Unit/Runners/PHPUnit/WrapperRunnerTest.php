<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\WrapperRunner;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\BaseRunner
 * @covers \ParaTest\Runners\PHPUnit\WrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\Worker\WrapperWorker
 * @covers \ParaTest\Runners\PHPUnit\WorkerCrashedException
 */
final class WrapperRunnerTest extends RunnerTestCase
{
    /** {@inheritdoc } */
    protected string $runnerClass = WrapperRunner::class;

    public function testWrapperRunnerNotAvailableInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--functional'] = true;

        $this->expectException(InvalidArgumentException::class);

        $this->runRunner();
    }

  /**
   * @see github.com/paratestphp/paratest/pull/540
   * we test that everything is okey with few tests
   * was problem that phpunit reset global variables in phpunit-wrapper, and tests fails
   */
    public function testWrapperRunnerWorksWellWithManyTests(): void
    {
        $this->bareOptions['--path']          = $this->fixture('passing_tests' . DS . 'level1' . DS . 'level2');
        $this->bareOptions['--configuration'] = $this->bareOptions['--configuration']  = $this->fixture('phpunit-parallel-suite-with-globals.xml');

        $this->runRunner();
    }
}
