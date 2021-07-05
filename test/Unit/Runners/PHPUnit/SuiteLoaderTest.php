<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\FullSuite;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\SuiteLoader;
use ParaTest\Tests\TestBase;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_keys;
use function array_shift;
use function count;
use function preg_match;
use function strstr;
use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\SuiteLoader
 */
final class SuiteLoaderTest extends TestBase
{
    private BufferedOutput $output;

    protected function setUpTest(): void
    {
        $this->output = new BufferedOutput();
    }

    public function testLoadThrowsExceptionWithInvalidPath(): void
    {
        $this->bareOptions['--path'] = '/path/to/nowhere';

        $this->expectException(RuntimeException::class);

        $this->loadSuite();
    }

    public function testLoadBarePathWithNoPathAndNoConfiguration(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No path or configuration provided (tests must end with Test.php)');

        $this->loadSuite(__DIR__);
    }

    public function testLoadTestsuiteFileFromConfig(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-file.xml');

        $loader = $this->loadSuite();
        $files  = $this->getObjectValue($loader, 'files');

        $expected = 1;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteFilesFromConfigWhileIgnoringExcludeTag(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-excluded-including-file.xml');
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        $expected = 1;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteFilesFromDirFromConfigWhileRespectingExcludeTag(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-excluded-including-dir.xml');
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        $expected = 2;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteFilesFromConfig(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-multifile.xml');
        $this->bareOptions['--group']         = 'fixtures,group4';
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        static::assertCount(3, $files);
    }

    public function testLoadTestsuiteWithDirectory(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-passing.xml');
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests'));
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteWithDirectories(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-multidir.xml');
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests')) +
            count($this->findTests(FIXTURES . DS . 'failing_tests'));
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteWithFilesDirsMixed(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-files-dirs-mix.xml');
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'failing_tests')) + 2;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteWithDuplicateFilesDirMixed(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-files-dirs-mix-duplicates.xml');
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests')) + 2;
        static::assertCount($expected, $files);
    }

    public function testLoadSomeTestsuite(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-parallel-suite.xml');
        $this->bareOptions['--testsuite']     = 'Suite 1';
        $loader                               = $this->loadSuite();
        $files                                = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'parallel_suite' . DS . 'One'));
        static::assertCount($expected, $files);
    }

    public function testLoadSuiteFromConfigWithBadSuitePath(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-non-existent-testsuite-dir.xml');
        $this->bareOptions['--testsuite']     = uniqid();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Suite path \w+ could not be found/');

        $this->loadSuite();
    }

    public function testLoadFileGetsPathOfFile(): void
    {
        $path  = $this->fixture('failing_tests' . DS . 'UnitTestWithClassAnnotationTest.php');
        $paths = $this->getLoadedPaths($path);
        static::assertEquals($path, array_shift($paths));
    }

    /**
     * @return string[]
     */
    private function getLoadedPaths(string $path, ?SuiteLoader $loader = null): array
    {
        $loader ??= new SuiteLoader($this->createOptionsFromArgv(['--path' => $path]), $this->output);
        $loader->load();
        $loaded = $this->getObjectValue($loader, 'loadedSuites');

        return array_keys($loaded);
    }

    public function testLoadFileShouldLoadFileWhereNameDoesNotEndInTest(): void
    {
        $path  = $this->fixture('passing_tests' . DS . 'TestOfUnits.php');
        $paths = $this->getLoadedPaths($path);
        static::assertEquals($path, array_shift($paths));
    }

    /**
     * @return ExecutableTest[]
     */
    public function testLoadDirGetsPathOfAllTestsWithKeys(): array
    {
        $fixturePath = $this->fixture('passing_tests');
        $files       = $this->findTests($fixturePath);

        $loader = new SuiteLoader($this->createOptionsFromArgv(['--path' => $fixturePath]), $this->output);
        $loader->load();
        $loaded = $this->getObjectValue($loader, 'loadedSuites');
        foreach ($loaded as $path => $test) {
            static::assertContains($path, $files);
        }

        return $loaded;
    }

    /**
     * @param ExecutableTest[] $paraSuites
     *
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testFirstParallelSuiteHasCorrectFunctions(array $paraSuites): void
    {
        $first     = $this->suiteByPath('GroupsTest.php', $paraSuites);
        $functions = $first->getFunctions();
        static::assertCount(5, $functions);
        static::assertEquals('testTruth', $functions[0]->getName());
        static::assertEquals('testFalsehood', $functions[1]->getName());
        static::assertEquals('testArrayLength', $functions[2]->getName());
        static::assertEquals('testStringLength', $functions[3]->getName());
        static::assertEquals('testAddition', $functions[4]->getName());
    }

    /**
     * @param ExecutableTest[] $paraSuites
     */
    private function suiteByPath(string $path, array $paraSuites): Suite
    {
        foreach ($paraSuites as $completePath => $suite) {
            if (strstr($completePath, $path) !== false) {
                static::assertInstanceOf(Suite::class, $suite);

                return $suite;
            }
        }

        throw new RuntimeException("Suite {$path} not found.");
    }

    /**
     * @param ExecutableTest[] $paraSuites
     *
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testSecondParallelSuiteHasCorrectFunctions(array $paraSuites): void
    {
        $second    = $this->suiteByPath('LegacyNamespaceTest.php', $paraSuites);
        $functions = $second->getFunctions();
        static::assertCount(1, $functions);
    }

    public function testGetTestMethodsOnlyReturnsMethodsOfGroupIfOptionIsSpecified(): void
    {
        $this->bareOptions['--path']  = $this->fixture('passing_tests/GroupsTest.php');
        $this->bareOptions['--group'] = 'group1';
        $loader                       = $this->loadSuite();
        $methods                      = $loader->getTestMethods();
        static::assertCount(2, $methods);
        static::assertEquals('testTruth', $methods[0]->getName());
        static::assertEquals('testFalsehood', $methods[1]->getName());
    }

    public function testGetTestMethodsOnlyReturnsMethodsOfClassGroup(): void
    {
        $this->bareOptions['--path']  = $this->fixture('passing_tests/GroupsTest.php');
        $this->bareOptions['--group'] = 'group4';
        $loader                       = $this->loadSuite();
        $methods                      = $loader->getTestMethods();
        static::assertCount(1, $loader->getSuites());
        static::assertCount(5, $methods);
    }

    public function testGetSuitesForNonMatchingGroups(): void
    {
        $this->bareOptions['--path']  = $this->fixture('passing_tests/GroupsTest.php');
        $this->bareOptions['--group'] = 'non-existent';
        $loader                       = $this->loadSuite();
        static::assertCount(0, $loader->getSuites());
        static::assertCount(0, $loader->getTestMethods());
    }

    public function testLoadIgnoresFilesWithoutClasses(): void
    {
        $this->bareOptions['--path']  = $this->fixture('special_classes/FileWithoutClass.php');
        $this->bareOptions['--group'] = 'non-existent';
        $loader                       = $this->loadSuite();
        static::assertCount(0, $loader->getTestMethods());
    }

    public function testExcludeGroupSwitchDontExecuteThatGroup(): void
    {
        $this->bareOptions['--path']          = $this->fixture('passing_tests/GroupsTest.php');
        $this->bareOptions['--exclude-group'] = 'group1';
        $loader                               = $this->loadSuite();
        static::assertCount(3, $loader->getTestMethods());
    }

    public function testGroupsSwitchExecutesMultipleGroups(): void
    {
        $this->bareOptions['--path']  = $this->fixture('passing_tests/GroupsTest.php');
        $this->bareOptions['--group'] = 'group1,group3';
        $loader                       = $this->loadSuite();
        static::assertCount(3, $loader->getTestMethods());
    }

    public function testExecutableTestsForFunctionalModeUse(): void
    {
        $this->bareOptions['--path'] = $this->fixture('passing_tests/DependsOnChain.php');
        $loader                      = $this->loadSuite();
        $tests                       = $loader->getTestMethods();
        static::assertCount(2, $tests);
        $testMethod = $tests[0];
        static::assertEquals('testOneA|testOneBDependsOnA|testOneCDependsOnB', $testMethod->getName());
        $testMethod = $tests[1];
        static::assertEquals('testTwoA|testTwoBDependsOnA', $testMethod->getName());
    }

    public function testParallelSuite(): void
    {
        $this->bareOptions['--configuration']  = $this->fixture('phpunit-parallel-suite.xml');
        $this->bareOptions['--parallel-suite'] = true;
        $this->bareOptions['--processes']      = '2';
        $loader                                = $this->loadSuite();
        $suites                                = $loader->getSuites();

        static::assertCount(2, $suites);
        foreach ($suites as $suite) {
            static::assertInstanceOf(FullSuite::class, $suite);
        }
    }

    public function testBatches(): void
    {
        $this->bareOptions['--path']           = $this->fixture('dataprovider_tests/DataProviderTest.php');
        $this->bareOptions['--bootstrap']      = BOOTSTRAP;
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--filter']         = 'testNumericDataProvider1000';
        $this->bareOptions['--max-batch-size'] = 50;
        $loader                                = $this->loadSuite(__DIR__);
        $suites                                = $loader->getSuites();

        static::assertCount(1, $suites);

        $suite = array_shift($suites);

        static::assertInstanceOf(Suite::class, $suite);
        static::assertCount(20, $suite->getFunctions());
    }

    public function testRunWithFatalParseErrors(): void
    {
        $this->bareOptions['--path'] = $this->fixture('fatal_tests' . DS . 'UnitTestWithFatalParseErrorTest.php');

        self::expectException(ParseError::class);

        $this->loadSuite();
    }

    public function testCacheIsWarmedWhenSpecified(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-coverage-cache.xml');
        $this->loadSuite();

        static::assertStringContainsString('Warming cache', $this->output->fetch());
    }

    public function testTestMethodsOfParentClassesAreCorrectlyLoaded(): void
    {
        $this->bareOptions['--path'] = $this->fixture('failing_tests');
        $loader                      = $this->loadSuite();

        static::assertCount(24, $loader->getTestMethods());
    }

    public function testTestMethodsFromErroringDataProviderReturnTheSimpleMethodToBeRunInTheSubprocess(): void
    {
        $this->bareOptions['--path'] = $this->fixture('github' . DS . 'GH565');
        $loader                      = $this->loadSuite();

        static::assertCount(3, $loader->getTestMethods());
    }

    /**
     * @return string[]
     */
    private function findTests(string $dir): array
    {
        $it    = new RecursiveDirectoryIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
        $it    = new RecursiveIteratorIterator($it);
        $files = [];
        foreach ($it as $file) {
            $match = preg_match('/Test\.php$/', $file->getPathname());
            self::assertNotFalse($match);
            if ($match === 0) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    private function loadSuite(?string $cwd = null): SuiteLoader
    {
        $options = $this->createOptionsFromArgv($this->bareOptions, $cwd);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();

        return $loader;
    }
}
