<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Command;

use Kiosky\Kiosky\Service\LegacyMigrationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kiosky:migrate:legacy-state',
    description: 'Migrates the legacy state blob idempotently into TYPO3 records.',
)]
final class LegacyMigrateCommand extends Command
{
    public function __construct(private readonly LegacyMigrationService $migration)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pid', null, InputOption::VALUE_REQUIRED, 'Storage page UID', '0');
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Write data; without this flag the command is a dry run.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pid = (int)$input->getOption('pid');
        if ($pid <= 0) {
            $output->writeln('<error>A positive --pid storage page is required.</error>');
            return self::INVALID;
        }
        $execute = (bool)$input->getOption('execute');
        $result = $this->migration->migrate($pid, $execute);
        $output->writeln($execute ? '<info>Migration completed.</info>' : '<comment>Dry run; no data changed.</comment>');
        foreach ($result['collections'] as $name => $count) {
            $output->writeln(sprintf('%s: %d', $name, $count));
        }
        $output->writeln(sprintf(
            'Would create/created: %d; already mapped: %d; invalid: %d',
            $result['created'],
            $result['skipped'],
            $result['invalid'],
        ));
        return $result['invalid'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
