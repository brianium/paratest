<?php

use \Habitat\Habitat;
use \Symfony\Component\Process\Process;

class FunctionalTestBase extends \PHPUnit\Framework\TestCase
{
    protected function fixture($fixture)
    {
        $fixture = FIXTURES . DS . $fixture;
        if (!file_exists($fixture)) {
            throw new Exception("Fixture $fixture not found");
        }

        return $fixture;
    }

    protected function invokeParatest($path, $options = array(), $callback = null)
    {
        $invoker = new ParaTestInvoker($this->fixture($path), BOOTSTRAP);
        return $invoker->execute($options, $callback);
    }

    protected function assertTestsPassed(Process $proc, $testPattern = '\d+', $assertionPattern = '\d+')
    {
        $output = $proc->getOutput();
        $this->assertRegExp(
            "/OK \($testPattern tests?, $assertionPattern assertions?\)/",
            $output
        );
        $this->assertEquals(0, $proc->getExitCode());
    }
}
