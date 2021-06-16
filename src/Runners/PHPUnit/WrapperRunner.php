<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;

use function array_shift;
use function assert;
use function count;
use function max;
use function usleep;

/**
 * @internal
 */
final class WrapperRunner extends BaseRunner
{
    /** @var WrapperWorker[] */
    private $workers = [];

    protected function beforeLoadChecks(): void
    {
        if ($this->options->functional()) {
            throw new InvalidArgumentException(
                'The `functional` option is not supported yet in the WrapperRunner. Only full classes can be run due ' .
                'to the current PHPUnit commands causing classloading issues.'
            );
        }
    }

    protected function doRun(): void
    {
        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->sendStopMessages();
        $this->waitForAllToFinish();
    }

    private function startWorkers(): void
    {
        for ($token = 1; $token <= $this->options->processes(); ++$token) {
            $this->workers[$token] = new WrapperWorker($this->output, $this->options, $token);
            $this->workers[$token]->start();
        }
    }

    private function assignAllPendingTests(): void
    {
        $phpunit        = $this->options->phpunit();
        $phpunitOptions = $this->options->filtered();

        while (count($this->pending) > 0 && count($this->workers) > 0) {
            foreach ($this->workers as $worker) {
                if (! $worker->isRunning()) {
                    throw $worker->getWorkerCrashedException();
                }

                if (! $worker->isFree()) {
                    continue;
                }

                $this->flushWorker($worker);
                if ($this->exitcode > 0 && $this->options->stopOnFailure()) {
                    $this->pending = [];
                } elseif (($pending = array_shift($this->pending)) !== null) {
                    $worker->assign($pending, $phpunit, $phpunitOptions, $this->options);
                }
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    private function flushWorker(WrapperWorker $worker): void
    {
        $reader = $worker->printFeedback($this->printer);

        if ($this->hasCoverage()) {
            $coverageMerger = $this->getCoverage();
            assert($coverageMerger !== null);
            if (($coverageFileName = $worker->getCoverageFileName()) !== null) {
                $coverageMerger->addCoverageFromFile($coverageFileName);
            }
        }

        $worker->reset();

        if ($reader === null) {
            return;
        }

        $exitCode = self::SUCCESS_EXIT;
        if ($reader->getTotalErrors() > 0) {
            $exitCode = self::EXCEPTION_EXIT;
        } elseif ($reader->getTotalFailures() > 0 || $reader->getTotalWarnings() > 0) {
            $exitCode = self::FAILURE_EXIT;
        }

        $this->exitcode = max($this->exitcode, $exitCode);
    }

    private function sendStopMessages(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
    }

    private function waitForAllToFinish(): void
    {
        while (count($this->workers) > 0) {
            foreach ($this->workers as $index => $worker) {
                if ($worker->isRunning()) {
                    continue;
                }

                if (! $worker->isFree()) {
                    throw $worker->getWorkerCrashedException();
                }

                $this->flushWorker($worker);
                unset($this->workers[$index]);
            }

            usleep(self::CYCLE_SLEEP);
        }
    }
}
