<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileRepository;

final readonly class DisplayApiService
{
    private const DISPLAY_TABLE = 'tx_kiosky_domain_model_display';
    private const HEARTBEAT_TABLE = 'tx_kiosky_domain_model_displayheartbeat';
    private const COMMAND_TABLE = 'tx_kiosky_domain_model_displaycommand';

    public function __construct(
        private ConnectionPool $connectionPool,
        private FileRepository $fileRepository,
    ) {}

    /** @return array<string, mixed>|null */
    public function authenticate(string $uuid, string $token): ?array
    {
        if ($token === '' || strlen($token) > 512) {
            return null;
        }
        $query = $this->connectionPool->getQueryBuilderForTable(self::DISPLAY_TABLE);
        $display = $query->select('*')->from(self::DISPLAY_TABLE)
            ->where(
                $query->expr()->eq('public_uuid', $query->createNamedParameter($uuid)),
                $query->expr()->eq('deleted', 0),
                $query->expr()->eq('hidden', 0),
            )
            ->executeQuery()->fetchAssociative();
        if (!$display || !password_verify($token, (string)$display['token_hash'])) {
            return null;
        }
        return $display;
    }

    /** @param array<string, mixed> $display */
    public function state(array $display): array
    {
        return [
            'data' => [
                'uuid' => $display['public_uuid'],
                'status' => $display['status'],
                'channel' => (int)$display['current_channel'],
                'playlist' => (int)$display['current_playlist'],
                'serverTime' => time(),
            ],
            'meta' => ['apiVersion' => 'v1'],
        ];
    }

    /** @param array<string, mixed> $display */
    public function content(array $display): array
    {
        $channel = (int)($display['current_channel'] ?: $display['default_channel']);
        $playlist = (int)($display['current_playlist'] ?: $display['default_playlist']);
        if ($playlist === 0 && $channel > 0) {
            $query = $this->connectionPool->getQueryBuilderForTable('tx_kiosky_domain_model_channel');
            $playlist = (int)$query->select('playlist')->from('tx_kiosky_domain_model_channel')
                ->where($query->expr()->eq('uid', $query->createNamedParameter($channel, ParameterType::INTEGER)))
                ->executeQuery()->fetchOne();
        }
        $items = $playlist > 0 ? $this->playlistItems($playlist) : [];
        return [
            'data' => [
                'channel' => $channel,
                'playlist' => $playlist,
                'fallbackBackground' => (int)$display['default_background'],
                'resolution' => ['width' => (int)$display['width'], 'height' => (int)$display['height']],
                'orientation' => $display['orientation'],
                'items' => $items,
            ],
            'meta' => ['apiVersion' => 'v1', 'generatedAt' => time()],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function playlistItems(int $playlist): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable('tx_kiosky_playlist_slide_mm');
        $relations = $query->select('uid_foreign', 'duration', 'transition_name')
            ->from('tx_kiosky_playlist_slide_mm')
            ->where($query->expr()->eq('uid_local', $query->createNamedParameter($playlist, ParameterType::INTEGER)))
            ->orderBy('sorting')
            ->executeQuery()->fetchAllAssociative();
        $items = [];
        foreach ($relations as $relation) {
            $slideQuery = $this->connectionPool->getQueryBuilderForTable('tx_kiosky_domain_model_slide');
            $slide = $slideQuery->select('*')->from('tx_kiosky_domain_model_slide')
                ->where(
                    $slideQuery->expr()->eq('uid', $slideQuery->createNamedParameter((int)$relation['uid_foreign'], ParameterType::INTEGER)),
                    $slideQuery->expr()->eq('deleted', 0),
                    $slideQuery->expr()->eq('hidden', 0),
                )
                ->executeQuery()->fetchAssociative();
            if (!$slide || !$this->enabled($slide)) {
                continue;
            }
            $document = json_decode((string)($slide['content_json'] ?? ''), true);
            $fileReferences = $this->fileRepository->findByRelation(
                'tx_kiosky_domain_model_slide',
                'media',
                (int)$slide['uid'],
            );
            $mediaUrl = $fileReferences !== [] ? $fileReferences[0]->getPublicUrl() : null;
            $items[] = [
                'uuid' => $slide['public_uuid'],
                'name' => $slide['name'],
                'type' => $slide['slide_type'],
                'document' => is_array($document) ? $document : [],
                'mediaUrl' => $mediaUrl,
                'duration' => max(1, (int)($relation['duration'] ?: $slide['duration'])),
                'transition' => $relation['transition_name'] ?: $slide['transition_name'],
            ];
        }
        return $items;
    }

    /** @param array<string, mixed> $record */
    private function enabled(array $record): bool
    {
        $now = time();
        return ((int)($record['starttime'] ?? 0) === 0 || (int)$record['starttime'] <= $now)
            && ((int)($record['endtime'] ?? 0) === 0 || (int)$record['endtime'] > $now);
    }

    /** @param array<string, mixed> $display
     *  @param array<string, mixed> $payload
     */
    public function heartbeat(array $display, array $payload, string $ipAddress): void
    {
        $now = time();
        $version = mb_substr((string)($payload['playerVersion'] ?? ''), 0, 64);
        $connection = $this->connectionPool->getConnectionForTable(self::DISPLAY_TABLE);
        $connection->update(self::DISPLAY_TABLE, [
            'last_heartbeat' => $now,
            'player_version' => $version,
            'status' => 'online',
            'tstamp' => $now,
        ], ['uid' => (int)$display['uid']], [ParameterType::INTEGER]);
        $connection->insert(self::HEARTBEAT_TABLE, [
            'pid' => (int)$display['pid'],
            'tstamp' => $now,
            'crdate' => $now,
            'display' => (int)$display['uid'],
            'received_at' => $now,
            'player_version' => $version,
            'ip_hash' => hash('sha256', $ipAddress),
            'state_json' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @param array<string, mixed> $display */
    public function acknowledge(array $display, string $commandUuid): bool
    {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $commandUuid)) {
            return false;
        }
        $connection = $this->connectionPool->getConnectionForTable(self::COMMAND_TABLE);
        return $connection->update(self::COMMAND_TABLE, [
            'status' => 'acknowledged',
            'acknowledged_at' => time(),
            'tstamp' => time(),
        ], ['display' => (int)$display['uid'], 'command_uuid' => $commandUuid]) === 1;
    }
}
