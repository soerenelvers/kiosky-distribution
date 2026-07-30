<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Command;

use Kiosky\Kiosky\Service\DisplayTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'kiosky:display:token',
    description: 'Regenerates a display token and prints the plaintext exactly once.',
)]
final class DisplayTokenCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly DisplayTokenService $tokens,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('uid', InputArgument::REQUIRED, 'Display record UID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uid = (int)$input->getArgument('uid');
        if ($uid <= 0) {
            $output->writeln('<error>Display UID must be positive.</error>');
            return self::INVALID;
        }
        $connection = $this->connectionPool->getConnectionForTable('tx_kiosky_domain_model_display');
        if ($connection->count('*', 'tx_kiosky_domain_model_display', ['uid' => $uid, 'deleted' => 0]) !== 1) {
            $output->writeln('<error>Display not found.</error>');
            return self::FAILURE;
        }
        $generated = $this->tokens->generate();
        $connection->update('tx_kiosky_domain_model_display', [
            'token_hash' => $generated['hash'],
            'tstamp' => time(),
        ], ['uid' => $uid]);
        $output->writeln('<comment>Store this token now. It cannot be displayed again:</comment>');
        $output->writeln($generated['token']);
        return self::SUCCESS;
    }
}
