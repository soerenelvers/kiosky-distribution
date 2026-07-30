<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Persistence;

use Doctrine\DBAL\ParameterType;
use Kiosky\Core\StateStore;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class Typo3StateStore implements StateStore
{
    private const TABLE = 'tx_kiosky_state';

    public function __construct(private ConnectionPool $connectionPool)
    {
    }

    public function load(): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $query->select('state_json')->from(self::TABLE)->where(
            $query->expr()->eq('uid', $query->createNamedParameter(1, ParameterType::INTEGER))
        )->executeQuery()->fetchAssociative();
        if (!$row || empty($row['state_json'])) return [];
        $state = json_decode((string)$row['state_json'], true);
        return is_array($state) ? $state : [];
    }

    public function save(array $state): void
    {
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        if ($connection->count('*', self::TABLE, ['uid' => 1]) > 0) {
            $connection->update(self::TABLE, ['state_json' => $json, 'updated_at' => time()], ['uid' => 1]);
            return;
        }
        $connection->insert(self::TABLE, ['uid' => 1, 'state_json' => $json, 'updated_at' => time()]);
        if ($connection->count('*', self::TABLE, ['uid' => 1]) !== 1) {
            throw new RuntimeException('Kiosky-Daten konnten nicht in TYPO3 gespeichert werden.', 500);
        }
    }
}
