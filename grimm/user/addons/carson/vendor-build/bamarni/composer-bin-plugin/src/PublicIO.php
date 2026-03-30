<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\Bamarni\Composer\Bin;

use BoldMinded\Carson\Dependency\Composer\IO\ConsoleIO;
use BoldMinded\Carson\Dependency\Symfony\Component\Console\Input\InputInterface;
use BoldMinded\Carson\Dependency\Symfony\Component\Console\Output\OutputInterface;
final class PublicIO extends ConsoleIO
{
    public static function fromConsoleIO(ConsoleIO $io) : self
    {
        return new self($io->input, $io->output, $io->helperSet);
    }
    public function getInput() : InputInterface
    {
        return $this->input;
    }
    public function getOutput() : OutputInterface
    {
        return $this->output;
    }
}
