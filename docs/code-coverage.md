# Code coverage generation with `paratest`
`paratest` is able to generate code coverage in multiple output formats:
````
 --coverage-clover      Generate code coverage report in Clover XML format.
 --coverage-cobertura   Generate code coverage report in Cobertura XML format.
 --coverage-crap4j      Generate code coverage report in Crap4J XML format.
 --coverage-html        Generate code coverage report in HTML format.
 --coverage-php         Serialize PHP_CodeCoverage object to file.
 --coverage-text        Generate code coverage report in text format.
 --coverage-xml         Generate code coverage report in PHPUnit XML format.
````

It uses the corresponding 
[code coverage options on the underlying `phpunit` library](https://phpunit.readthedocs.io/en/7.4/code-coverage-analysis.html). 
`phpunit` itself relies on "external" help
to get the coverage information (usually either via `xdebug` or `phpdbg`). Further, it requires
a correctly set-up `phpunit.xml` configuration file (including a `coverage` filter).

## Preparing the `phpunit.xml` configuration file
The configuration file **must** include a `<coverage>` element that specifies a `<include>`, see
- [Including Files](https://phpunit.readthedocs.io/en/9.5/code-coverage-analysis.html#including-files)
- [<include> element within <coverage>](https://phpunit.readthedocs.io/en/9.5/configuration.html#appendixes-configuration-coverage-include)

**CAUTION: Making a mistake here is very often the reason for failing code coverage reports!**

## Code coverage with xdebug
First, check if `xdebug` is enabled:
````
php -m | grep xdebug
xdebug
````
or
````
php -v
PHP 7.2.14-1+ubuntu16.04.1+deb.sury.org+1 (cli) (built: Jan 13 2019 10:05:18) ( NTS )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.2.0, Copyright (c) 1998-2018 Zend Technologies
    with Xdebug v2.6.1, Copyright (c) 2002-2018, by Derick Rethans
````

If the output is empty, you are either missing the extension completely or have not [activated](#xdebug-activation) it. See the sections below 
for troubleshooting and [installation](#xdebug-installation) help.

If `xdebug` is up and running, you can simply use `vendor/bin/paratest --coverage-text` to check if the code coverage generation is 
working in general. Example for `paratest`s unit test suite:

````
bin/paratest -p 4 --coverage-text test/unit

Running phpunit in 4 process with /codebase/paratest/vendor/phpunit/phpunit/phpunit

Configuration read from /codebase/paratest/phpunit.xml.dist

...............................................................  63 / 155 ( 40%)
............................................................... 126 / 157 ( 80%)
.....................................

Time: 12.2 seconds, Memory: 8.00MB

OK (163 tests, 328 assertions)


Code Coverage Report:
  2019-01-25 09:41:26

 Summary:
  Classes: 22.86% (8/35)
  Methods: 49.47% (139/281)
  Lines:   59.38% (896/1509)

\ParaTest\Console::ParaTest\Console\VersionProvider
  Methods:  50.00% ( 3/ 6)   Lines:  81.08% ( 30/ 37)
\ParaTest\Console\Commands::ParaTest\Console\Commands\ParaTestCommand
  Methods: 100.00% ( 4/ 4)   Lines: 100.00% ( 24/ 24)
\ParaTest\Console\Testers::ParaTest\Console\Testers\PHPUnit
  Methods:  18.18% ( 2/11)   Lines:  29.73% ( 22/ 74)
\ParaTest\Coverage::ParaTest\Coverage\CoverageMerger
  Methods:  25.00% ( 1/ 4)   Lines:  19.05% (  4/ 21)
\ParaTest\Logging::ParaTest\Logging\LogInterpreter
  Methods:  81.82% ( 9/11)   Lines:  93.75% ( 45/ 48)
\ParaTest\Logging::ParaTest\Logging\MetaProvider
  Methods:  66.67% ( 2/ 3)   Lines:  93.75% ( 15/ 16)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\Reader
  Methods:  81.82% ( 9/11)   Lines:  97.01% ( 65/ 67)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\TestCase
  Methods:  71.43% ( 5/ 7)   Lines:  92.68% ( 38/ 41)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\TestSuite
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 27/ 27)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\Writer
  Methods: 100.00% (10/10)   Lines: 100.00% ( 57/ 57)
\ParaTest\Parser::ParaTest\Parser\ParsedClass
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 12/ 12)
\ParaTest\Parser::ParaTest\Parser\ParsedFunction
  Methods:  50.00% ( 1/ 2)   Lines:  75.00% (  3/  4)
\ParaTest\Parser::ParaTest\Parser\ParsedObject
  Methods: 100.00% ( 4/ 4)   Lines: 100.00% ( 10/ 10)
\ParaTest\Parser::ParaTest\Parser\Parser
  Methods:  87.50% ( 7/ 8)   Lines:  96.15% ( 50/ 52)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\BaseRunner
  Methods:  16.67% ( 2/12)   Lines:   8.93% (  5/ 56)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Configuration
  Methods:  83.33% (10/12)   Lines:  94.03% ( 63/ 67)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\ExecutableTest
  Methods:  52.17% (12/23)   Lines:  63.16% ( 48/ 76)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Options
  Methods:  66.67% ( 8/12)   Lines:  92.63% ( 88/ 95)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\ResultPrinter
  Methods:  53.57% (15/28)   Lines:  74.66% (109/146)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Runner
  Methods:  36.36% ( 4/11)   Lines:  23.19% ( 16/ 69)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Suite
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% (  5/  5)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\SuiteLoader
  Methods:  44.44% ( 8/18)   Lines:  78.91% (101/128)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\SuitePath
  Methods: 100.00% ( 5/ 5)   Lines: 100.00% ( 10/ 10)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\TestFileLoader
  Methods:  83.33% ( 5/ 6)   Lines:  94.74% ( 36/ 38)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\TestMethod
  Methods:  60.00% ( 3/ 5)   Lines:  45.45% (  5/ 11)
\ParaTest\Util::ParaTest\Util\Str
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  8/  8)

````

If you want to enable [xdebug on premise](#on-premise-activation), it gets a little bit more complicated due to the subprocess structure that `paratest` uses.
We need to not only invoke `paratest` itself with `xdebug` enabled but need to also tell it to invoke the subprocesses in that way.
This can can be done with the [`--passthru-php` option](https://github.com/paratestphp/paratest/issues/360).

Assuming that 
- xdebug is installed but deactivated
- the extension is located at `/usr/lib/php/20170718/xdebug.so`

you can invoke code coverage generation via 
`php '-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so' vendor/bin/paratest --coverage-text --passthru-php="'-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so'"`

This will 
- invoke paratest with xdebug enabled (`php '-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so' vendor/bin/paratest`)
- invoke the subprocesses with xdebug enabled (`--passthru-php="'-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so'"`)

Example for `paratest`s unit test suite:
````
php '-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so' bin/paratest -p 4 --coverage-text test/unit --passthru-php="'-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so' "

Running phpunit in 4 processes with /codebase/paratest/vendor/phpunit/phpunit/phpunit

Configuration read from /codebase/paratest/phpunit.xml.dist

...............................................................  63 / 155 ( 40%)
............................................................... 126 / 157 ( 80%)
.....................................

Time: 8.93 seconds, Memory: 8.00MB

OK (163 tests, 328 assertions)


Code Coverage Report:
  2019-01-25 10:41:04

 Summary:
  Classes: 22.86% (8/35)
  Methods: 49.47% (139/281)
  Lines:   59.38% (896/1509)

\ParaTest\Console::ParaTest\Console\VersionProvider
  Methods:  50.00% ( 3/ 6)   Lines:  81.08% ( 30/ 37)
\ParaTest\Console\Commands::ParaTest\Console\Commands\ParaTestCommand
  Methods: 100.00% ( 4/ 4)   Lines: 100.00% ( 24/ 24)
\ParaTest\Console\Testers::ParaTest\Console\Testers\PHPUnit
  Methods:  18.18% ( 2/11)   Lines:  29.73% ( 22/ 74)
\ParaTest\Coverage::ParaTest\Coverage\CoverageMerger
  Methods:  25.00% ( 1/ 4)   Lines:  19.05% (  4/ 21)
\ParaTest\Logging::ParaTest\Logging\LogInterpreter
  Methods:  81.82% ( 9/11)   Lines:  93.75% ( 45/ 48)
\ParaTest\Logging::ParaTest\Logging\MetaProvider
  Methods:  66.67% ( 2/ 3)   Lines:  93.75% ( 15/ 16)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\Reader
  Methods:  81.82% ( 9/11)   Lines:  97.01% ( 65/ 67)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\TestCase
  Methods:  71.43% ( 5/ 7)   Lines:  92.68% ( 38/ 41)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\TestSuite
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 27/ 27)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\Writer
  Methods: 100.00% (10/10)   Lines: 100.00% ( 57/ 57)
\ParaTest\Parser::ParaTest\Parser\ParsedClass
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 12/ 12)
\ParaTest\Parser::ParaTest\Parser\ParsedFunction
  Methods:  50.00% ( 1/ 2)   Lines:  75.00% (  3/  4)
\ParaTest\Parser::ParaTest\Parser\ParsedObject
  Methods: 100.00% ( 4/ 4)   Lines: 100.00% ( 10/ 10)
\ParaTest\Parser::ParaTest\Parser\Parser
  Methods:  87.50% ( 7/ 8)   Lines:  96.15% ( 50/ 52)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\BaseRunner
  Methods:  16.67% ( 2/12)   Lines:   8.93% (  5/ 56)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Configuration
  Methods:  83.33% (10/12)   Lines:  94.03% ( 63/ 67)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\ExecutableTest
  Methods:  52.17% (12/23)   Lines:  63.16% ( 48/ 76)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Options
  Methods:  66.67% ( 8/12)   Lines:  92.63% ( 88/ 95)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\ResultPrinter
  Methods:  53.57% (15/28)   Lines:  74.66% (109/146)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Runner
  Methods:  36.36% ( 4/11)   Lines:  23.19% ( 16/ 69)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Suite
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% (  5/  5)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\SuiteLoader
  Methods:  44.44% ( 8/18)   Lines:  78.91% (101/128)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\SuitePath
  Methods: 100.00% ( 5/ 5)   Lines: 100.00% ( 10/ 10)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\TestFileLoader
  Methods:  83.33% ( 5/ 6)   Lines:  94.74% ( 36/ 38)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\TestMethod
  Methods:  60.00% ( 3/ 5)   Lines:  45.45% (  5/ 11)
\ParaTest\Util::ParaTest\Util\Str
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  8/  8)
````

### Using phpunits `--prepend` option for faster code coverage
`xdebug` supports filtering for which files it generates code coverage via `xdebug_set_filter`. 
In January 2019, Sebastian Bergmann published [Faster Code Coverage](https://thephp.cc/news/2019/01/faster-code-coverage) and explains
on how this functionality can be integrated with `phpunit`. Please refer to the article for further information.

To get this to work in `paratest`, we'll need to use the `--passthru` option for now. That option enables passing verbatim arguments
to the underlying phpunit command. Example for `paratest`:

First, im creating `xdebug-filter.php` in the root of the repository with
````
<?php declare(strict_types=1);
if (!\function_exists('xdebug_set_filter')) {
    throw new Exception("xdebug_set_filter not available on system");
}

\xdebug_set_filter(
    \XDEBUG_FILTER_CODE_COVERAGE,
    \XDEBUG_PATH_WHITELIST,
    [
        __DIR__.'/src/Util',
    ]
);
````
Note that we restrict xdebugs code coverage now to the `src/Util` directory.

Then, I run `bin/paratest -p 1 --coverage-text --passthru="'--prepend' 'xdebug-filter.php'" test/unit` to run only the unit tests. 
Result:
````
php -m | grep xdebug
xdebug

bin/paratest -p 4 --coverage-text --passthru="'--prepend' 'xdebug-filter.php'" test/unit

Running phpunit in 4 process with /codebase/paratest/vendor/phpunit/phpunit/phpunit

Configuration read from /codebase/paratest/phpunit.xml.dist

...............................................................  63 / 155 ( 40%)
............................................................... 126 / 157 ( 80%)
.....................................

Time: 6.45 seconds, Memory: 4.00MB

OK (163 tests, 328 assertions)


Code Coverage Report:
  2019-01-25 11:07:16

 Summary:
  Classes:  2.86% (1/35)
  Methods:  0.36% (1/281)
  Lines:    0.35% (8/2311)

\ParaTest\Util::ParaTest\Util\Str
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  8/  8)
````
Note that this is *much* faster as before, *but* also only contains coverage for `src/Util`. Usually, we would set the filter to `src`,
but for the sake of demonstrating the functionality I'm using `src/Util`.

The above example assumes that xdebug is activated. You can also combine this approach with on-premise activation:
````
`php '-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so' vendor/bin/paratest --coverage-text --passthru-php="'-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so'" --passthru="'--prepend' 'xdebug-filter.php'"`
````

### xdebug installation
- [Official installation guide](https://xdebug.org/docs/install)
- [Install via apt-get](http://www.dieuwe.com/blog/xdebug-ubuntu-1604-php7)
  - Note: For the latest PHP versions you might need to update your apt repositories first, usually via `add-apt-repository ppa:ondrej/php && apt-get update`.
    See also https://tecadmin.net/install-php-7-on-ubuntu/
- [Install in docker](https://www.pascallandau.com/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#xdebug-php)
- [Install on windows](https://www.pascallandau.com/blog/php7-with-xdebug-2-4-for-phpstorm-on-windows-10/#installing-xdebug)

### xdebug activation
- Generally: Make sure the `xdebug` extension is loaded, i.e. your `php.ini` must contain the line
  ````
  zend_extension=xdebug.so
  ````
  where `xdebug.so` can also be the path to the actual `.so` file (e.g. `/usr/lib/php/20180731/xdebug.so`).
  Hint: Usually this is not put directly in the `php.ini` but in `conf.d/xdebug.ini` where `conf.d/` is the the 
  directory that `php.ini` uses to load additional `.ini` files.
  If you don't know where the extension is located on your system, `sudo find / -name xdebug.so` might help:
  ````
  sudo find / -name xdebug.so
  /usr/lib/php/20160303/xdebug.so
  /usr/lib/php/20180731/xdebug.so
  /usr/lib/php/20170718/xdebug.so
  /usr/lib/php/20151012/xdebug.so
  /usr/lib/php/20131226/xdebug.so
  ````
- If you are running ubuntu, you can probably just use `phpenmod` to activate the extension:
  ````
  sudo phpenmod xdebug
  ````
- [For Docker](https://www.pascallandau.com/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#xdebug-php):
  ````
  docker-php-ext-enable xdebug
  ````
  
### On-premise activation
You can also enable `xdebug` on premise via [`-d` flag](http://php.net/manual/en/features.commandline.options.php) with
`php '-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so'`:
````
php -v
PHP 7.2.14-1+ubuntu16.04.1+deb.sury.org+1 (cli) (built: Jan 13 2019 10:05:18) ( NTS )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.2.0, Copyright (c) 1998-2018 Zend Technologies
    
php '-d' 'zend_extension=/usr/lib/php/20170718/xdebug.so' -v
PHP 7.2.14-1+ubuntu16.04.1+deb.sury.org+1 (cli) (built: Jan 13 2019 10:05:18) ( NTS )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.2.0, Copyright (c) 1998-2018 Zend Technologies
    with Xdebug v2.6.1, Copyright (c) 2002-2018, by Derick Rethans
````

Oftentimes this is a desired feature because having `xdebug` enabled comes with a performance penalty.

## Code coverage with phpdbg
`phpdbg` [has built up a reputation](https://hackernoon.com/generating-code-coverage-with-phpunite-and-phpdbg-4d20347ffb45) 
as a much faster tool to generate code coverage compared to xdebug.
You can find the official introduction at http://php.net/phpdbg

First, check if `phpdbg` is available on your system:
```` 
which phpdbg
/usr/bin/phpdbg
````
If the result is empty, `phpdbg` is probably missing from your system. See [phpdbg installation](#phpdbg-installation) for further instructions.

If `phpdbg` is available, you need to invoke paratest e.g. via  `phpdbg -qrr vendor/bin/paratest --coverage-text`.
Example for `paratest`s unit test suite:

````
phpdbg -qrr bin/paratest -p 4 --coverage-text test/unit

Running phpunit in 4 processes with /codebase/paratest/vendor/phpunit/phpunit/phpunit

Configuration read from /codebase/paratest/phpunit.xml.dist

...............................................................  63 / 155 ( 40%)
............................................................... 126 / 157 ( 80%)
.....................................

Time: 6.67 seconds, Memory: 16.00MB

OK (163 tests, 328 assertions)


Code Coverage Report:
  2019-01-25 10:56:12

 Summary:
  Classes: 25.71% (9/35)
  Methods: 50.71% (142/280)
  Lines:   60.31% (825/1368)

\ParaTest\Console::ParaTest\Console\VersionProvider
  Methods:  66.67% ( 4/ 6)   Lines:  86.21% ( 25/ 29)
\ParaTest\Console\Commands::ParaTest\Console\Commands\ParaTestCommand
  Methods: 100.00% ( 4/ 4)   Lines: 100.00% ( 22/ 22)
\ParaTest\Console\Testers::ParaTest\Console\Testers\PHPUnit
  Methods:  18.18% ( 2/11)   Lines:  26.76% ( 19/ 71)
\ParaTest\Coverage::ParaTest\Coverage\CoverageMerger
  Methods:  25.00% ( 1/ 4)   Lines:  20.00% (  4/ 20)
\ParaTest\Logging::ParaTest\Logging\LogInterpreter
  Methods:  81.82% ( 9/11)   Lines:  95.65% ( 44/ 46)
\ParaTest\Logging::ParaTest\Logging\MetaProvider
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 15/ 15)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\Reader
  Methods:  81.82% ( 9/11)   Lines:  96.83% ( 61/ 63)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\TestCase
  Methods:  71.43% ( 5/ 7)   Lines:  94.29% ( 33/ 35)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\TestSuite
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 26/ 26)
\ParaTest\Logging\JUnit::ParaTest\Logging\JUnit\Writer
  Methods: 100.00% (10/10)   Lines: 100.00% ( 53/ 53)
\ParaTest\Parser::ParaTest\Parser\ParsedClass
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% ( 11/ 11)
\ParaTest\Parser::ParaTest\Parser\ParsedFunction
  Methods:  50.00% ( 1/ 2)   Lines:  66.67% (  2/  3)
\ParaTest\Parser::ParaTest\Parser\ParsedObject
  Methods: 100.00% ( 4/ 4)   Lines: 100.00% (  9/  9)
\ParaTest\Parser::ParaTest\Parser\Parser
  Methods:  87.50% ( 7/ 8)   Lines:  96.08% ( 49/ 51)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\BaseRunner
  Methods:  16.67% ( 2/12)   Lines:   8.51% (  4/ 47)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Configuration
  Methods:  75.00% ( 9/12)   Lines:  90.62% ( 58/ 64)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\ExecutableTest
  Methods:  54.55% (12/22)   Lines:  65.22% ( 45/ 69)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Options
  Methods:  75.00% ( 9/12)   Lines:  93.33% ( 84/ 90)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\ResultPrinter
  Methods:  53.57% (15/28)   Lines:  75.00% ( 99/132)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Runner
  Methods:  36.36% ( 4/11)   Lines:  21.67% ( 13/ 60)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\Suite
  Methods: 100.00% ( 3/ 3)   Lines: 100.00% (  4/  4)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\SuiteLoader
  Methods:  50.00% ( 9/18)   Lines:  78.15% ( 93/119)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\SuitePath
  Methods: 100.00% ( 5/ 5)   Lines: 100.00% (  9/  9)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\TestFileLoader
  Methods:  83.33% ( 5/ 6)   Lines:  94.12% ( 32/ 34)
\ParaTest\Runners\PHPUnit::ParaTest\Runners\PHPUnit\TestMethod
  Methods:  60.00% ( 3/ 5)   Lines:  40.00% (  4/ 10)
\ParaTest\Util::ParaTest\Util\Str
  Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  7/  7)
````

### `phpdbg` installation
`phpdbg` ships with `php-src` and can be compiled as per https://github.com/krakjoe/phpdbg#installation

However, you can usually install it simply via `apt-get` with `apt-get update && apt-get install php-phpdbg`.
(Note: I've never done this for Windows).

## Troubleshooting
If anything goes wrong, you should enable debug output via `--verbose=1`. This should give you an idea what comamnds `paratest`
is actually running. Example:

````
bin/paratest -p 1 test/unit/Util/StrTest.php --verbose=1 --runner WrapperRunner

Running phpunit in 1 process with /codebase/paratest/vendor/phpunit/phpunit/phpunit

Configuration read from /codebase/paratest/phpunit.xml.dist

Starting WrapperWorker via: PARATEST=1 XDEBUG_CONFIG="true" TEST_TOKEN=1 UNIQUE_TEST_TOKEN=5c4af2d88c0d9 /usr/bin/php7.2  "/codebase/paratest/bin/phpunit-wrapper.php"

Executing test via: '/codebase/paratest/vendor/phpunit/phpunit/phpunit' '--configuration' '/codebase/paratest/phpunit.xml.dist' '--log-junit' '/tmp/PT_tIUjT5' 'ParaTest\Util\StrTest' 'test/unit/Util/StrTest.php'
.....

Time: 604 ms, Memory: 4.00MB

OK (5 tests, 5 assertions)
````

### Error: Coverage file /tmp/CV_B8u8f3 is empty. Xdebug is disabled! Enable for coverage.
`paratest` can't find xdebug. Please see [xdebug installation](#xdebug-installation) and [xdebug activation](#xdebug-activation)

### Error: Coverage file /tmp/CV_ZV3Wdd is empty. This means a PHPUnit process has crashed.
Unfortunately, this error is very generic. It just means that _something_ has gone wrong but we basically don't know what.
Things to check:
- is your `/tmp` folder writable?
- check if your phpunit configuration file is correct (see [Preparing the `phpunit.xml` configuration file](#preparing-the-phpunit-xml-configuration-file))
