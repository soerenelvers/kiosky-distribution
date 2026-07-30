<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Command;

use Kiosky\Kiosky\Service\ApiFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kiosky:crewbrain:sync',
    description: 'Führt den fälligen automatischen CrewBrain-Import aus.',
)]
final class CrewBrainSyncCommand extends Command
{
    public function __construct(private readonly ApiFactory $apiFactory)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $summary = $this->apiFactory
            ->create(['id' => 'typo3:command', 'name' => 'TYPO3 Console', 'email' => '', 'role' => 'admin'])
            ->runScheduledTasks();
        $output->writeln(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
    }
}
