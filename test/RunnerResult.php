<?php

declare(strict_types=1);

namespace ParaTest\Tests;

final class RunnerResult
{
    private int $exitCode;
    private string $output;

    public function __construct(int $exitCode, string $output)
    {
        $this->exitCode = $exitCode;
        $this->output   = $output;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
