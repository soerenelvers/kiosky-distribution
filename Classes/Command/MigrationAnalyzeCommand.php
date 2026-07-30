<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Command;

use Kiosky\Kiosky\Persistence\Typo3StateStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kiosky:migrate:analyze',
    description: 'Analyzes the legacy Kiosky JSON state without changing data.',
)]
final class MigrationAnalyzeCommand extends Command
{
    public function __construct(private readonly Typo3StateStore $store)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $state = $this->store->load();
        if ($state === []) {
            $output->writeln('<info>No legacy state found.</info>');
            return self::SUCCESS;
        }
        $output->writeln('<info>Legacy state detected. No data was changed.</info>');
        foreach ($state as $key => $value) {
            $count = is_array($value) ? count($value) : 1;
            $output->writeln(sprintf('%s: %d', (string)$key, $count));
        }
        return self::SUCCESS;
    }
}
