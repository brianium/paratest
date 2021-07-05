<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use InvalidArgumentException;
use ParaTest\Logging\MetaProviderInterface;
use SimpleXMLElement;

use function array_merge;
use function assert;
use function count;
use function current;
use function file_exists;
use function file_get_contents;
use function filesize;
use function unlink;

/**
 * @internal
 */
final class Reader implements MetaProviderInterface
{
    private SimpleXMLElement $xml;

    private bool $isSingle = false;

    /** @var TestSuite[] */
    private array $suites = [];

    /** @var string */
    private $logFile;

    public function __construct(string $logFile)
    {
        if (! file_exists($logFile)) {
            throw new InvalidArgumentException("Log file {$logFile} does not exist");
        }

        $this->logFile = $logFile;
        if (filesize($logFile) === 0) {
            throw new InvalidArgumentException(
                "Log file {$logFile} is empty. This means a PHPUnit process has crashed."
            );
        }

        $logFileContents = file_get_contents($this->logFile);
        assert($logFileContents !== false);
        $this->xml = new SimpleXMLElement($logFileContents);
        $this->init();
    }

    /**
     * Returns whether or not this reader contains only
     * a single suite.
     */
    public function isSingleSuite(): bool
    {
        return $this->isSingle;
    }

    /**
     * Return the Reader's collection
     * of test suites.
     *
     * @return TestSuite[]
     */
    public function getSuites(): array
    {
        return $this->suites;
    }

    /**
     * Return an array that contains
     * each suite's instant feedback. Since
     * logs do not contain skipped or incomplete
     * tests this array will contain any number of the following
     * characters: .,F,E
     * TODO: Update this, skipped was added in phpunit.
     *
     * @return string[]
     */
    public function getFeedback(): array
    {
        $feedback = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            foreach ($suite->cases as $case) {
                if (count($case->errors) > 0) {
                    $feedback[] = 'E';
                } elseif (count($case->warnings) > 0) {
                    $feedback[] = 'W';
                } elseif (count($case->failures) > 0) {
                    $feedback[] = 'F';
                } elseif (count($case->risky) > 0) {
                    $feedback[] = 'R';
                } elseif (count($case->skipped) > 0) {
                    $feedback[] = 'S';
                } else {
                    $feedback[] = '.';
                }
            }
        }

        return $feedback;
    }

    /**
     * Remove the JUnit xml file.
     */
    public function removeLog(): void
    {
        unlink($this->logFile);
    }

    /**
     * Initialize the suite collection
     * from the JUnit xml document.
     */
    private function init(): void
    {
        $this->initSuite();
        $cases = $this->getCaseNodes();
        foreach ($cases as $nodeArray) {
            $this->initSuiteFromCases($nodeArray);
        }
    }

    /**
     * Uses an array of testcase nodes to build a suite.
     *
     * @param SimpleXMLElement[] $nodeArray an array of SimpleXMLElement nodes representing testcase elements
     */
    private function initSuiteFromCases(array $nodeArray): void
    {
        $testCases = [];
        $testSuite = $this->caseNodesToSuite($nodeArray, $testCases);
        if (! $this->isSingle) {
            $testSuite->cases          = $testCases;
            $this->suites[0]->suites[] = $testSuite;
        } else {
            $suite        = $this->suites[0];
            $suite->cases = array_merge($suite->cases, $testCases);
        }
    }

    /**
     * Fold an array of testcase nodes into a suite array.
     *
     * @param SimpleXMLElement[] $nodeArray an array of testcase nodes
     * @param TestCase[]         $testCases an array reference. Individual testcases will be placed here.
     */
    private function caseNodesToSuite(array $nodeArray, array &$testCases = []): TestSuite
    {
        $testSuite = TestSuite::empty();
        foreach ($nodeArray as $simpleXMLElement) {
            $testCase    = TestCase::caseFromNode($simpleXMLElement);
            $testCases[] = $testCase;

            $testSuite->name = $testCase->class;
            $testSuite->file = $testCase->file;
            ++$testSuite->tests;
            $testSuite->assertions += $testCase->assertions;
            $testSuite->failures   += count($testCase->failures);
            $testSuite->errors     += count($testCase->errors);
            $testSuite->warnings   += count($testCase->warnings);
            $testSuite->skipped    += count($testCase->skipped);
            $testSuite->time       += $testCase->time;
        }

        return $testSuite;
    }

    /**
     * Return a collection of testcase nodes
     * from the xml document.
     *
     * @return array<string, SimpleXMLElement[]>
     */
    private function getCaseNodes(): array
    {
        $caseNodes = $this->xml->xpath('//testcase');
        $cases     = [];
        foreach ($caseNodes as $node) {
            $caseFilename = (string) $node['file'];
            if (! isset($cases[$caseFilename])) {
                $cases[$caseFilename] = [];
            }

            $cases[$caseFilename][] = $node;
        }

        return $cases;
    }

    /**
     * Determine if this reader is a single suite
     * and initialize the suite collection with the first
     * suite.
     */
    private function initSuite(): void
    {
        $suiteNodes     = $this->xml->xpath('/testsuites/testsuite/testsuite');
        $this->isSingle = count($suiteNodes) === 0;

        $node = $this->xml->xpath('/testsuites/testsuite');
        $node = current($node);

        if ($node !== false) {
            $this->suites[] = new TestSuite(
                (string) $node['name'],
                (int) $node['tests'],
                (int) $node['assertions'],
                (int) $node['failures'],
                (int) $node['errors'],
                (int) $node['warnings'],
                (int) $node['skipped'],
                (float) $node['time'],
                (string) $node['file']
            );
        } else {
            $this->suites[] = TestSuite::empty();
        }
    }

    public function getTotalTests(): int
    {
        return $this->suites[0]->tests;
    }

    public function getTotalAssertions(): int
    {
        return $this->suites[0]->assertions;
    }

    public function getTotalErrors(): int
    {
        return $this->suites[0]->errors;
    }

    public function getTotalFailures(): int
    {
        return $this->suites[0]->failures;
    }

    public function getTotalWarnings(): int
    {
        return $this->suites[0]->warnings;
    }

    public function getTotalSkipped(): int
    {
        return $this->suites[0]->skipped;
    }

    public function getTotalTime(): float
    {
        return $this->suites[0]->time;
    }

    /**
     * {@inheritDoc}
     */
    public function getErrors(): array
    {
        $messages = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            foreach ($suite->cases as $case) {
                foreach ($case->errors as $msg) {
                    $messages[] = $msg['text'];
                }
            }
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getWarnings(): array
    {
        $messages = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            foreach ($suite->cases as $case) {
                foreach ($case->warnings as $msg) {
                    $messages[] = $msg['text'];
                }
            }
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getFailures(): array
    {
        $messages = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            foreach ($suite->cases as $case) {
                foreach ($case->failures as $msg) {
                    $messages[] = $msg['text'];
                }
            }
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getRisky(): array
    {
        $messages = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            foreach ($suite->cases as $case) {
                foreach ($case->risky as $msg) {
                    $messages[] = $msg['text'];
                }
            }
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getSkipped(): array
    {
        $messages = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            foreach ($suite->cases as $case) {
                foreach ($case->skipped as $msg) {
                    $messages[] = $msg['text'];
                }
            }
        }

        return $messages;
    }
}
