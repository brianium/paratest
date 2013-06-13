<?php

class FunctionalTestBase extends PHPUnit_Framework_TestCase
{
    protected $bootstrap;
    protected $path;
    protected $exitCode = -1;
    private   $errorOutput;

    public function setUp()
    {
        $this->path = FIXTURES . DS . 'tests';
        $this->bootstrap = dirname(FIXTURES) . DS . 'bootstrap.php';
        $this->errorOutput = '';
    }

    protected function getPhpunitOutput()
    {
        $cmd = sprintf("%s --bootstrap %s %s", PHPUNIT, $this->bootstrap, $this->path);

        return $this->getTestOutput($cmd);
    }

    protected function setErrorOutput($errorOutput)
    {
        return $this->errorOutput = $errorOutput;
    }

    protected function getErrorOutput()
    {
        return $this->errorOutput;
    }

    protected function getParaTestOutput($functional = false, $options = array())
    {
        $cmd = sprintf("%s --bootstrap %s --phpunit %s", PARA_BINARY, $this->bootstrap, PHPUNIT);
        if($functional) $cmd .= ' --functional';
        foreach($options as $switch => $value)
            $cmd .= sprintf(" %s",
                           $this->getOption($switch, $value));
        $cmd .= sprintf(" %s", $this->path);

        return $this->getTestOutput($cmd);
    }

    protected function getOption($switch, $value) {
        if(strlen($switch) > 1) $switch = '--' . $switch;
        else $switch = '-' . $switch;

        return $value ? $switch . ' ' . $value : $switch;
    }

    protected function getTestOutput($cmd)
    {
        $proc = $this->getFinishedProc($cmd, $pipes);
        $output = $proc->getOutput();
        $this->setErrorOutput($proc->getErrorOutput());

        return $output;
    }

    protected function getFinishedProc($cmd, &$pipes)
    {
        $proc = new \Symfony\Component\Process\Process($cmd, null, array('PATH' => getenv('PATH')));
        $this->waitForProc($proc);

        return $proc;
    }

    protected function waitForProc(\Symfony\Component\Process\Process $proc)
    {
        $proc->start();
        while($proc->isRunning()) {
            usleep(1000);
        }
        $this->exitCode = $proc->getExitCode();
    }

    protected function getExitCode()
    {
        return $this->exitCode;
    }

    protected function normalizeStr($string)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            return str_replace("\r\n", "\n", $string);
        }
        return $string;
    }
}
