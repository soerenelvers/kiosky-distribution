<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

use Kiosky\Kiosky\Persistence\Typo3StateStore;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class LegacyMigrationService
{
    private const MAP_TABLE = 'tx_kiosky_migration_map';

    /**
     * collection => [table, field mapping]
     * @var array<string, array{0:string,1:array<string,string>}>
     */
    private const COLLECTIONS = [
        'locations' => ['tx_kiosky_domain_model_location', [
            'name' => 'name', 'organisation' => 'organization', 'street' => 'street',
            'house_number' => 'houseNumber', 'postal_code' => 'postalCode', 'city' => 'city',
            'country' => 'country', 'building' => 'building', 'floor' => 'floor',
            'room' => 'room', 'timezone' => 'timezone', 'description' => 'description',
        ]],
        'groups' => ['tx_kiosky_domain_model_displaygroup', [
            'name' => 'name', 'description' => 'description',
        ]],
        'displays' => ['tx_kiosky_domain_model_display', [
            'name' => 'name', 'identifier' => 'slug', 'room' => 'room',
            'orientation' => 'orientation', 'player_version' => 'playerVersion',
            'status' => 'status', 'description' => 'description',
        ]],
        'channels' => ['tx_kiosky_domain_model_channel', [
            'name' => 'name', 'description' => 'description', 'priority' => 'priority',
        ]],
        'slides' => ['tx_kiosky_domain_model_slide', [
            'name' => 'name', 'slide_type' => 'type', 'duration' => 'duration',
            'priority' => 'priority', 'transition_name' => 'transition',
        ]],
        'templates' => ['tx_kiosky_domain_model_template', [
            'name' => 'name', 'description' => 'description', 'category' => 'category',
            'width' => 'width', 'height' => 'height', 'orientation' => 'orientation',
        ]],
        'events' => ['tx_kiosky_domain_model_event', [
            'title' => 'title', 'subtitle' => 'subtitle', 'description' => 'description',
            'hall' => 'room', 'organizer' => 'organizer', 'notes' => 'publicNotes',
            'status' => 'status', 'external_id' => 'externalId',
        ]],
        'presets' => ['tx_kiosky_domain_model_preset', [
            'name' => 'name', 'description' => 'description', 'priority' => 'priority',
        ]],
        'schedules' => ['tx_kiosky_domain_model_schedule', [
            'name' => 'name', 'target_type' => 'targetType', 'content_type' => 'contentType',
            'recurrence' => 'recurrence', 'priority' => 'priority',
        ]],
    ];

    public function __construct(
        private Typo3StateStore $store,
        private ConnectionPool $connectionPool,
    ) {}

    /** @return array{created:int, skipped:int, invalid:int, collections:array<string,int>} */
    public function migrate(int $pid, bool $execute): array
    {
        $state = $this->store->load();
        $result = ['created' => 0, 'skipped' => 0, 'invalid' => 0, 'collections' => []];
        foreach (self::COLLECTIONS as $collection => [$table, $mapping]) {
            $records = is_array($state[$collection] ?? null) ? $state[$collection] : [];
            $result['collections'][$collection] = count($records);
            foreach ($records as $record) {
                if (!is_array($record) || trim((string)($record['id'] ?? '')) === '') {
                    $result['invalid']++;
                    continue;
                }
                $sourceId = (string)$record['id'];
                if ($this->mapped($collection, $sourceId)) {
                    $result['skipped']++;
                    continue;
                }
                if (!$execute) {
                    $result['created']++;
                    continue;
                }
                $data = ['pid' => $pid, 'tstamp' => time(), 'crdate' => time(), 'cruser_id' => 0];
                foreach ($mapping as $target => $source) {
                    if (array_key_exists($source, $record) && !is_array($record[$source])) {
                        $data[$target] = $record[$source];
                    }
                }
                if (array_key_exists('public_uuid', $this->tableColumns($table))) {
                    $data['public_uuid'] = $this->uuid($sourceId);
                }
                if ($collection === 'slides') {
                    $data['content_json'] = json_encode($record['document'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                } elseif ($collection === 'templates') {
                    $data['definition_json'] = json_encode($record['document'] ?? $record['definition'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                }
                $connection = $this->connectionPool->getConnectionForTable($table);
                $connection->insert($table, $data);
                $uid = (int)$connection->lastInsertId();
                $this->connectionPool->getConnectionForTable(self::MAP_TABLE)->insert(self::MAP_TABLE, [
                    'source_type' => $collection,
                    'source_id' => $sourceId,
                    'target_table' => $table,
                    'target_uid' => $uid,
                    'migrated_at' => time(),
                    'source_hash' => hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)),
                ]);
                $result['created']++;
            }
        }
        return $result;
    }

    private function mapped(string $type, string $id): bool
    {
        return $this->connectionPool->getConnectionForTable(self::MAP_TABLE)
            ->count('*', self::MAP_TABLE, ['source_type' => $type, 'source_id' => $id]) > 0;
    }

    /** @return array<string, mixed> */
    private function tableColumns(string $table): array
    {
        return $this->connectionPool->getConnectionForTable($table)->createSchemaManager()
            ->listTableColumns($table);
    }

    private function uuid(string $source): string
    {
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $source)) {
            return strtolower($source);
        }
        $hex = hash('sha256', 'kiosky-legacy:' . $source);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-5' . substr($hex, 13, 3)
            . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
    }
}
