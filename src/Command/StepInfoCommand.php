<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand('app:step:info')]
class StepInfoCommand
{
    public function __invoke(OutputInterface $output, CacheInterface $cache): int
    {
        $step = $cache->get('app.current_step', function ($item) {
            $process = new Process(['git', 'tag', '-l', '--points-at', 'HEAD']);
            $process->mustRun();
            $item->expiresAfter(30);

            return $process->getOutput();
        });
        $output->writeln($step);

        return Command::SUCCESS;
    }
}