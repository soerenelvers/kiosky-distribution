<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'kiosky:housekeeping',
    description: 'Marks stale displays offline and removes expired operational records.',
)]
final class HousekeepingCommand extends Command
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('heartbeat-retention-days', null, InputOption::VALUE_REQUIRED, 'Heartbeat retention', '30');
        $this->addOption('audit-retention-days', null, InputOption::VALUE_REQUIRED, 'Audit retention', '180');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = time();
        $displayConnection = $this->connectionPool->getConnectionForTable('tx_kiosky_domain_model_display');
        $offline = $displayConnection->executeStatement(
            'UPDATE tx_kiosky_domain_model_display SET status = ?, tstamp = ? WHERE deleted = 0 AND last_heartbeat > 0 AND last_heartbeat < ?',
            ['offline', $now, $now - 120],
        );
        $heartbeatDays = max(1, (int)$input->getOption('heartbeat-retention-days'));
        $auditDays = max(1, (int)$input->getOption('audit-retention-days'));
        $heartbeats = $this->connectionPool->getConnectionForTable('tx_kiosky_domain_model_displayheartbeat')
            ->executeStatement(
                'DELETE FROM tx_kiosky_domain_model_displayheartbeat WHERE received_at < ?',
                [$now - ($heartbeatDays * 86400)],
            );
        $audits = $this->connectionPool->getConnectionForTable('tx_kiosky_domain_model_auditlog')
            ->executeStatement(
                'DELETE FROM tx_kiosky_domain_model_auditlog WHERE created_at < ?',
                [$now - ($auditDays * 86400)],
            );
        $output->writeln(sprintf('Offline: %d; heartbeats removed: %d; audit entries removed: %d', $offline, $heartbeats, $audits));
        return self::SUCCESS;
    }
}
