<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter,
    ParaTest\Logging\JUnit\Writer;

class WrapperRunner
{
    const PHPUNIT_FATAL_ERROR = 255;

    protected $pending = array();
    protected $running = array();
    protected $options;
    protected $interpreter;
    protected $printer;
    
    public function __construct($opts = array())
    {
        $this->options = new Options($opts);
        $this->interpreter = new LogInterpreter();
        $this->printer = new ResultPrinter($this->interpreter);
    }

    public function run()
    {
        $this->verifyConfiguration();
        $this->load();
        $this->printer->start($this->options);

        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->sendStopMessages();
        $this->waitForAllToFinish();
        $this->complete();
    }

    private function verifyConfiguration()
    {
        if (isset($this->options->filtered['configuration']) && !file_exists($this->options->filtered['configuration']->getPath())) {
            $this->printer->println(sprintf('Could not read "%s".', $this->options->filtered['configuration']));
            exit(1);
        }
    }

    private function load()
    {
        $loader = new SuiteLoader($this->options);
        $loader->load($this->options->path);
        $executables = ($this->options->functional) ? $loader->getTestMethods() : $loader->getSuites();
        $this->pending = array_merge($this->pending, $executables);
        foreach($this->pending as $pending)
            $this->printer->addTest($pending);
    }

    private function startWorkers()
    {
        for ($token = 1; $token <= $this->options->processes; $token++) {
            $worker = new Worker();
            $worker->start($token);
            $this->streams[] = $worker->stdout();
            $this->workers[] = $worker;
        }
    }

    private function assignAllPendingTests()
    {
        $phpunit = $this->options->phpunit . ' --no-globals-backup';
        $phpunitOptions = $this->options->filtered;
        while(count($this->pending) 
            && $this->waitForStreamsToChange($this->streams)) {
            foreach($this->progressedWorkers() as $worker) {
                if($worker->isFree()) {
                    $worker->printFeedback($this->printer);
                    $pending = array_shift($this->pending);
                    $worker->assign($pending, $phpunit, $phpunitOptions);
                }
            }
        }
    }

    private function sendStopMessages()
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
    }

    private function waitForAllToFinish()
    {
        $toStop = $this->workers;
        while (count($toStop) > 0) {
            $toCheck = $this->streamsOf($toStop);
            $new = $this->waitForStreamsToChange($toCheck);
            foreach ($this->progressedWorkers() as $index => $worker) {
                if (!$worker->isRunning()) {
                    $worker->printFeedback($this->printer);
                    unset($toStop[$index]);
                }
            }
        }
    }

    // put on WorkersPool
    private function waitForStreamsToChange($modified)
    {
        $write = array();
        $except = array();
        $result = stream_select($modified, $write, $except, 1);
        if ($result === false) {
            throw new \RuntimeException("stream_select() returned an error while waiting for all workers to finish.");
        }
        $this->modified = $modified;
        return $result;
    }

    // put on WorkersPool
    private function progressedWorkers()
    {
        $result = array();
        foreach($this->modified as $modifiedStream) {
            $found = null;
            foreach ($this->streams as $index => $stream) {
                if ($modifiedStream == $stream) {
                    $found = $index;
                    break;
                }
            }
            $result[$found] = $this->workers[$found];
        }
        $this->modified = array();
        return $result;
    }

    /**
     * Returns the output streams of a subset of workers.
     * @param array    keys are positions in $this->workers
     * @return array
     */
    private function streamsOf($workers)
    {
        $streams = array();
        foreach (array_keys($workers) as $index) {
            $streams[$index] = $this->streams[$index];
        }
        return $streams;
    }

    private function complete()
    {
        $this->printer->printResults();
        $this->interpreter->rewind();
        $this->log();
        $readers = $this->interpreter->getReaders();
        foreach($readers as $reader) {
            $reader->removeLog();
        }
    }


    private function log()
    {
        if(!isset($this->options->filtered['log-junit'])) return;
        $output = $this->options->filtered['log-junit'];
        $writer = new Writer($this->interpreter, $this->options->path);
        $writer->write($output);
    }

    public function getExitCode()
    {
        return 0;
    }

    /**
    private function testIsStillRunning($test)
    {
        if(!$test->isDoneRunning()) return true;
        $this->setExitCode($test);
        $test->stop();
        if (static::PHPUNIT_FATAL_ERROR === $test->getExitCode())
            throw new \Exception($test->getStderr(), $test->getExitCode());
        return false;
    }
     */
}
