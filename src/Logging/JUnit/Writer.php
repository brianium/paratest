<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use DOMDocument;
use DOMElement;
use ParaTest\Logging\LogInterpreter;

use function assert;
use function count;
use function file_put_contents;
use function get_object_vars;
use function htmlspecialchars;
use function is_float;
use function is_scalar;
use function preg_match;
use function sprintf;
use function str_replace;

use const ENT_XML1;

/**
 * @internal
 */
final class Writer
{
    /**
     * The name attribute of the testsuite being
     * written.
     */
    private string $name;

    private LogInterpreter $interpreter;

    private DOMDocument $document;

    /**
     * A pattern for matching testsuite attributes.
     */
    private static string $suiteAttrs = '/name|(?:test|assertion|failure|error|warning)s|skipped|time|file/';

    /**
     * A pattern for matching testcase attrs.
     */
    private static string $caseAttrs = '/name|class|file|line|assertions|time/';

    public function __construct(LogInterpreter $interpreter, string $name)
    {
        $this->name                   = $name;
        $this->interpreter            = $interpreter;
        $this->document               = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;
    }

    /**
     * Get the name of the root suite being written.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the xml structure the writer
     * will use.
     */
    public function getXml(): string
    {
        $suites = $this->interpreter->flattenCases();
        $root   = $this->getSuiteRoot($suites);
        foreach ($suites as $suite) {
            $snode = $this->appendSuite($root, $suite);
            foreach ($suite->cases as $case) {
                $this->appendCase($snode, $case);
            }
        }

        $xml = $this->document->saveXML();
        assert($xml !== false);

        return $xml;
    }

    /**
     * Write the xml structure to a file path.
     */
    public function write(string $path): void
    {
        file_put_contents($path, $this->getXml());
    }

    /**
     * Append a testsuite node to the given
     * root element.
     */
    private function appendSuite(DOMElement $root, TestSuite $suite): DOMElement
    {
        $suiteNode = $this->document->createElement('testsuite');
        $vars      = get_object_vars($suite);
        foreach ($vars as $name => $value) {
            $match = preg_match(static::$suiteAttrs, $name);
            assert($match !== false);
            if ($match === 0) {
                continue;
            }

            assert(is_scalar($value));

            if ($name === 'time') {
                assert(is_float($value));
                $value = sprintf('%F', $value);
            }

            $suiteNode->setAttribute($name, (string) $value);
        }

        $root->appendChild($suiteNode);

        return $suiteNode;
    }

    /**
     * Append a testcase node to the given testsuite
     * node.
     */
    private function appendCase(DOMElement $suiteNode, TestCase $case): DOMElement
    {
        $caseNode = $this->document->createElement('testcase');
        $vars     = get_object_vars($case);
        foreach ($vars as $name => $value) {
            $matchCount = preg_match(static::$caseAttrs, $name);
            assert($matchCount !== false);
            if ($matchCount === 0) {
                continue;
            }

            assert(is_scalar($value));

            if ($this->isEmptyLineAttribute($name, $value)) {
                continue;
            }

            if ($name === 'time') {
                assert(is_float($value));
                $value = sprintf('%F', $value);
            }

            $caseNode->setAttribute($name, (string) $value);

            if ($name !== 'class') {
                continue;
            }

            $caseNode->setAttribute('classname', str_replace('\\', '.', (string) $value));
        }

        $suiteNode->appendChild($caseNode);
        $this->appendDefects($caseNode, $case->failures, 'failure');
        $this->appendDefects($caseNode, $case->errors, 'error');
        $this->appendDefects($caseNode, $case->warnings, 'warning');
        $this->appendDefects($caseNode, $case->risky, 'error');
        $this->appendDefects($caseNode, $case->skipped, 'skipped');

        return $caseNode;
    }

    /**
     * Append error or failure nodes to the given testcase node.
     *
     * @param array<int, array{type: string, text: string}> $defects
     */
    private function appendDefects(DOMElement $caseNode, array $defects, string $type): void
    {
        foreach ($defects as $defect) {
            if ($type === 'skipped') {
                $defectNode = $this->document->createElement($type);
            } else {
                $defectNode = $this->document->createElement($type, htmlspecialchars($defect['text'], ENT_XML1) . "\n");
                $defectNode->setAttribute('type', $defect['type']);
            }

            $caseNode->appendChild($defectNode);
        }
    }

    /**
     * Get the root level testsuite node.
     *
     * @param TestSuite[] $suites
     */
    private function getSuiteRoot(array $suites): DOMElement
    {
        $testsuites = $this->document->createElement('testsuites');
        $this->document->appendChild($testsuites);
        if (count($suites) === 1) {
            return $testsuites;
        }

        $rootSuite = $this->document->createElement('testsuite');
        $attrs     = $this->getSuiteRootAttributes($suites);
        foreach ($attrs as $attr => $value) {
            $rootSuite->setAttribute($attr, (string) $value);
        }

        $testsuites->appendChild($rootSuite);

        return $rootSuite;
    }

    /**
     * Get the attributes used on the root testsuite
     * node.
     *
     * @param TestSuite[] $suites
     *
     * @return array<string, int|float|string>
     */
    private function getSuiteRootAttributes(array $suites): array
    {
        $result = [
            'name' => $this->name,
            'tests' => 0,
            'assertions' => 0,
            'errors' => 0,
            'warnings' => 0,
            'failures' => 0,
            'skipped' => 0,
            'time' => 0,
        ];
        foreach ($suites as $suite) {
            $result['tests']      += $suite->tests;
            $result['assertions'] += $suite->assertions;
            $result['errors']     += $suite->errors;
            $result['warnings']   += $suite->warnings;
            $result['failures']   += $suite->failures;
            $result['skipped']    += $suite->skipped;
            $result['time']       += $suite->time;
        }

        return $result;
    }

    /**
     * Prevent writing empty "line" XML attributes which could break parsers.
     *
     * @param mixed $value
     */
    private function isEmptyLineAttribute(string $name, $value): bool
    {
        return $name === 'line' && empty($value);
    }
}
