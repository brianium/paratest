<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class EmptyRunnerStub implements RunnerInterface
{
    public const OUTPUT = 'EmptyRunnerStub EXECUTED';
    private Options $options;
    private OutputInterface $output;

    public function __construct(Options $options, OutputInterface $output)
    {
        $this->options = $options;
        $this->output  = $output;
    }

    public function run(): void
    {
        $this->output->writeln('Path: ' . $this->options->path());
        $this->output->writeln('Configuration: ' . (($conf = $this->options->configuration()) !== null
            ? $conf->filename()
            : ''
        ));
        $this->output->writeln(self::OUTPUT);
    }

    public function getExitCode(): int
    {
        return 0;
    }
}
