<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Command;

use Kiosky\Kiosky\Service\UpdateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kiosky:update:check', description: 'Prüft die konfigurierte Kiosky-Updatequelle auf eine neue stabile Version.')]
final class UpdateCheckCommand extends Command
{
    public function __construct(private readonly UpdateManager $updateManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $this->updateManager->check();
        if ($status['lastError']) {
            $output->writeln('<error>' . $status['lastError'] . '</error>');
            return self::FAILURE;
        }
        $output->writeln($status['updateAvailable']
            ? sprintf('<info>Kiosky %s ist verfügbar.</info>', $status['availableVersion'])
            : '<info>Kiosky ist aktuell.</info>');
        return self::SUCCESS;
    }
}
