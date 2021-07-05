<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

/**
 * A simple data structure for tracking
 * data associated with a testsuite node
 * in a JUnit xml document
 *
 * @internal
 */
final class TestSuite
{
    public string $name;

    public int $tests;

    public int $assertions;

    public int $failures;

    public int $errors;

    public int $warnings;

    public int $skipped;

    public float $time;

    public string $file;

    /**
     * Nested suites.
     *
     * @var TestSuite[]
     */
    public array $suites = [];

    /**
     * Cases belonging to this suite.
     *
     * @var TestCase[]
     */
    public array $cases = [];

    public function __construct(
        string $name,
        int $tests,
        int $assertions,
        int $failures,
        int $errors,
        int $warnings,
        int $skipped,
        float $time,
        string $file
    ) {
        $this->name       = $name;
        $this->tests      = $tests;
        $this->assertions = $assertions;
        $this->failures   = $failures;
        $this->skipped    = $skipped;
        $this->errors     = $errors;
        $this->warnings   = $warnings;
        $this->time       = $time;
        $this->file       = $file;
    }

    public static function empty(): self
    {
        return new self(
            '',
            0,
            0,
            0,
            0,
            0,
            0,
            0.0,
            '',
        );
    }
}
