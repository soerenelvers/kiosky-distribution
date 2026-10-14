<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Persistence;

use Doctrine\DBAL\ParameterType;
use Kiosky\Core\StateStore;
use Kiosky\Core\ApiAccess;
use Kiosky\Core\StateConflict;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class Typo3StateStore implements StateStore
{
    private const TABLE = 'tx_kiosky_state';
    private bool $loaded = false;
    private ?string $loadedRaw = null;

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    public function load(): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $query->select('state_json')->from(self::TABLE)->where(
            $query->expr()->eq('uid', $query->createNamedParameter(1, ParameterType::INTEGER))
        )->executeQuery()->fetchAssociative();
        $this->loaded = true;
        $this->loadedRaw = $row ? (string)$row['state_json'] : null;
        if (!$row || empty($row['state_json'])) return [];
        $state = json_decode((string)$row['state_json'], true);
        return is_array($state) ? $state : [];
    }

    public function save(array $state): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $row = $connection->fetchAssociative('SELECT state_json FROM '.self::TABLE.' WHERE uid = 1');
            if ($this->loaded && ($row ? (string)$row['state_json'] : null) !== $this->loadedRaw) throw new StateConflict();
            if (!$row) {
                $json = json_encode($state,JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                try {
                    $connection->insert(self::TABLE,['uid'=>1,'state_json'=>$json,'updated_at'=>time()]);
                    $this->loaded = true; $this->loadedRaw = $json;
                    return;
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $error) { continue; }
            }
            $current = json_decode((string)$row['state_json'],true) ?: [];
            $next = $state;
            if (isset($state['apiUsers']) || isset($current['apiUsers'])) $next['apiUsers'] = ApiAccess::mergeUsers($state['apiUsers'] ?? [],$current['apiUsers'] ?? []);
            $json = json_encode($next,JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === $row['state_json']) return;
            if ($connection->update(self::TABLE,['state_json'=>$json,'updated_at'=>time()],['uid'=>1,'state_json'=>$row['state_json']]) === 1) { $this->loaded = true; $this->loadedRaw = $json; return; }
        }
        throw new StateConflict();
    }
}
