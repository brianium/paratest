<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\TextUI\ResultPrinter;
use Throwable;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class NullPhpunitPrinter implements ResultPrinter
{
    public function printResult(TestResult $result): void
    {
    }

    public function write(string $buffer): void
    {
    }

    public function addError(Test $test, Throwable $t, float $time): void
    {
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
    }

    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
    }

    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
    }

    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
    }

    public function startTestSuite(TestSuite $suite): void
    {
    }

    public function endTestSuite(TestSuite $suite): void
    {
    }

    public function startTest(Test $test): void
    {
    }

    public function endTest(Test $test, float $time): void
    {
    }
}
