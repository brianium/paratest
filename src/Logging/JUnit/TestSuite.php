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
        public string $name,
        public int $tests,
        public int $assertions,
        public int $failures,
        public int $errors,
        public int $warnings,
        public int $skipped,
        public float $time,
        public string $file
    ) {
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
