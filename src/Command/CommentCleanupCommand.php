<?php

namespace App\Command;

use App\Repository\CommentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand('app:comment:cleanup', 'Deletes rejected and spam comments from the database')]
#[AsCronTask('50 23 * * *')]
class CommentCleanupCommand
{
    public function __invoke(
        SymfonyStyle $io,
        CommentRepository $commentRepository,
        #[Option(description: 'Dry run')]
        bool $dryRun = false,
    ): int {
        if ($dryRun) {
            $io->note('Dry mode enabled');

            $count = $commentRepository->countOldRejected();
        } else {
            $count = $commentRepository->deleteOldRejected();
        }

        $io->success(sprintf('Deleted "%d" old rejected/spam comments.', $count));

        return Command::SUCCESS;
    }
}