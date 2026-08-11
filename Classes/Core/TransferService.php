<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;

final class TransferService
{
    public const FORMAT = 'kiosky-system-transfer';
    public const VERSION = 2;
    private const SECTIONS = ['settings', 'events', 'displays', 'slides', 'channels', 'schedules', 'operations', 'users', 'api', 'integrations'];
    private const COLLECTION_LIMIT = 10000;

    /** @param array<string,mixed> $state @param list<string> $requested @param list<array<string,mixed>> $platformUsers */
    public function export(array $state, array $requested, string $platform, string $productVersion, array $platformUsers = []): array
    {
        $included = $this->selection($requested);
        $sections = [];
        if (in_array('settings', $included, true)) {
            $sections['settings'] = [
                'featureSettings' => $state['featureSettings'] ?? [],
                'navigationOrder' => $state['navigationOrder'] ?? [],
                'scheduleTargetOrder' => $state['scheduleTargetOrder'] ?? [],
                'titleExclusions' => $state['titleExclusions'] ?? [],
                'publicCalendarSettings' => PublicEventCalendar::normalizeSettings($state['publicCalendarSettings'] ?? []),
            ];
        }
        if (in_array('events', $included, true)) {
            $sections['events'] = ['records' => $this->active($state['events'] ?? []), 'scheduleEntries' => $state['eventSchedules'] ?? []];
        }
        if (in_array('slides', $included, true)) {
            $sections['slides'] = [
                'records' => $this->active($state['slides'] ?? []),
                'versions' => $state['slideVersions'] ?? [],
                'templates' => $this->active($state['templates'] ?? []),
                'mediaFolders' => $this->active($state['mediaFolders'] ?? []),
                'mediaAssets' => $this->active($state['mediaAssets'] ?? []),
            ];
        }
        if (in_array('channels', $included, true)) $sections['channels'] = ['records' => $this->active($state['channels'] ?? [])];
        if (in_array('displays', $included, true)) {
            $sections['displays'] = [
                'records' => array_map(static function (array $display): array {
                    unset($display['playerKey'], $display['player_key'], $display['pairingCode'], $display['pairing_code']);
                    return $display;
                }, $this->active($state['displays'] ?? [])),
                'groups' => $this->active($state['groups'] ?? []),
                'locations' => $this->active($state['locations'] ?? []),
                'matrices' => $this->active($state['matrices'] ?? []),
            ];
        }
        if (in_array('schedules', $included, true)) {
            $sections['schedules'] = ['records' => $this->active($state['schedules'] ?? []), 'assignments' => $state['assignments'] ?? []];
        }
        if (in_array('operations', $included, true)) {
            $sections['operations'] = [
                'presets' => $this->active($state['presets'] ?? []),
                'warningTemplates' => $this->active($state['warningTemplates'] ?? []),
                'warnings' => array_map(static function (array $warning): array {
                    if (($warning['status'] ?? '') === 'active') $warning['status'] = 'draft';
                    unset($warning['publishedAt'], $warning['endedAt']);
                    return $warning;
                }, $state['warnings'] ?? []),
                'dwd' => array_values($state['dwd'] ?? []),
            ];
        }
        if (in_array('users', $included, true)) {
            $users = array_merge($state['portableUsers'] ?? [], $platformUsers);
            $sections['users'] = ['records' => $this->uniqueBy($users, 'email')];
        }
        if (in_array('api', $included, true)) $sections['api'] = ['records' => $state['portableApiUsers'] ?? []];
        if (in_array('integrations', $included, true)) {
            $sections['integrations'] = [
                'activeSource' => $state['activeEventSource'] ?? null,
                'titleExclusions' => $state['titleExclusions'] ?? [],
                'crewbrain' => [
                    'config' => $this->withoutSecrets($state['crewbrain']['config'] ?? []),
                    'mapping' => $state['crewbrain']['mapping'] ?? [],
                ],
                'easyjob' => [
                    'config' => $this->withoutSecrets($state['easyjob']['config'] ?? []),
                    'mapping' => $state['easyjob']['mapping'] ?? [],
                ],
            ];
        }
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exportedAt' => gmdate('c'),
            'source' => ['platform' => $platform, 'version' => $productVersion],
            'requestedSections' => $requested ?: self::SECTIONS,
            'includedSections' => $included,
            'security' => ['containsSecrets' => false, 'credentialsRemoved' => true],
            'sections' => $sections,
        ];
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $package @return array<string,mixed> */
    public function import(array &$state, array $package): array
    {
        if (($package['format'] ?? null) !== self::FORMAT || ($package['version'] ?? null) !== self::VERSION || !is_array($package['sections'] ?? null)) {
            throw new RuntimeException('Die Datei ist kein unterstütztes Kiosky-Transferpaket.', 422);
        }
        $sections = $package['sections'];
        if (strlen(json_encode($sections, JSON_THROW_ON_ERROR)) > 250_000_000) throw new RuntimeException('Das Transferpaket ist zu groß.', 413);
        $maps = [];
        foreach ([
            'events' => $sections['events']['records'] ?? [],
            'slides' => $sections['slides']['records'] ?? [],
            'channels' => $sections['channels']['records'] ?? [],
            'locations' => $sections['displays']['locations'] ?? [],
            'displays' => $sections['displays']['records'] ?? [],
            'groups' => $sections['displays']['groups'] ?? [],
            'matrices' => $sections['displays']['matrices'] ?? [],
            'templates' => $sections['slides']['templates'] ?? [],
            'mediaFolders' => $sections['slides']['mediaFolders'] ?? [],
            'mediaAssets' => $sections['slides']['mediaAssets'] ?? [],
            'presets' => $sections['operations']['presets'] ?? [],
            'warningTemplates' => $sections['operations']['warningTemplates'] ?? [],
            'warnings' => $sections['operations']['warnings'] ?? [],
        ] as $collection => $records) {
            if (!is_array($records) || count($records) > self::COLLECTION_LIMIT) throw new RuntimeException('Das Transferpaket enthält eine ungültige Datenmenge.', 422);
            $maps[$collection] = [];
            foreach ($records as $record) {
                if (!is_array($record) || !is_string($record['id'] ?? null) || isset($maps[$collection][$record['id']])) throw new RuntimeException('Das Transferpaket enthält ungültige oder doppelte IDs.', 422);
                $maps[$collection][$record['id']] = $this->uuid();
            }
        }

        $counts = [];
        foreach (['events', 'slides', 'channels'] as $collection) {
            $records = $sections[$collection]['records'] ?? [];
            $rewritten = $this->rewriteRecords($records, $maps, $collection);
            $state[$collection] = array_merge($state[$collection] ?? [], $rewritten);
            $counts[$collection] = count($rewritten);
        }
        foreach (['locations', 'displays', 'groups', 'matrices'] as $collection) {
            $records = $collection === 'locations' ? ($sections['displays']['locations'] ?? [])
                : ($collection === 'groups' ? ($sections['displays']['groups'] ?? [])
                : ($collection === 'matrices' ? ($sections['displays']['matrices'] ?? []) : ($sections['displays']['records'] ?? [])));
            $rewritten = $this->rewriteRecords($records, $maps, $collection);
            $state[$collection] = array_merge($state[$collection] ?? [], $rewritten);
            $counts[$collection] = count($rewritten);
        }
        foreach (['templates', 'mediaFolders', 'mediaAssets'] as $collection) {
            $records = $sections['slides'][$collection] ?? [];
            $state[$collection] = array_merge($state[$collection] ?? [], $this->rewriteRecords($records, $maps, $collection));
            $counts[$collection] = count($records);
        }
        $versions = $sections['slides']['versions'] ?? [];
        $state['slideVersions'] = array_merge($state['slideVersions'] ?? [], $this->rewriteRecords($versions, $maps, 'slideVersions'));
        $eventSchedules = $sections['events']['scheduleEntries'] ?? [];
        $state['eventSchedules'] = array_merge($state['eventSchedules'] ?? [], $this->rewriteRecords($eventSchedules, $maps, 'eventSchedules'));

        $schedules = $this->rewriteRecords($sections['schedules']['records'] ?? [], $maps, 'schedules');
        $assignments = $this->rewriteRecords($sections['schedules']['assignments'] ?? [], $maps, 'assignments');
        $state['schedules'] = array_merge($state['schedules'] ?? [], $schedules);
        $state['assignments'] = array_merge($state['assignments'] ?? [], $assignments);
        $counts['schedules'] = count($schedules);

        foreach (['presets', 'warningTemplates', 'warnings'] as $collection) {
            $records = $this->rewriteRecords($sections['operations'][$collection] ?? [], $maps, $collection);
            if ($collection === 'warnings') {
                $records = array_map(static function (array $warning): array {
                    if (($warning['status'] ?? '') === 'active') $warning['status'] = 'draft';
                    unset($warning['publishedAt'], $warning['endedAt']);
                    return $warning;
                }, $records);
            }
            $state[$collection] = array_merge($state[$collection] ?? [], $records);
            $counts[$collection] = count($records);
        }
        $dwd = $this->rewriteRecords($sections['operations']['dwd'] ?? [], $maps, 'dwd');
        foreach ($dwd as $configuration) {
            $locationId = (string)($configuration['locationId'] ?? '');
            if ($locationId !== '') $state['dwd'][$locationId] = $configuration;
        }
        $counts['dwdConfigurations'] = count($dwd);
        if (isset($sections['settings']) && is_array($sections['settings'])) {
            $settings = $sections['settings'];
            if (is_array($settings['featureSettings'] ?? null)) $state['featureSettings'] = array_replace($state['featureSettings'] ?? [], $settings['featureSettings']);
            if (is_array($settings['navigationOrder'] ?? null)) $state['navigationOrder'] = array_values(array_map('strval', $settings['navigationOrder']));
            if (is_array($settings['scheduleTargetOrder'] ?? null)) $state['scheduleTargetOrder'] = array_values(array_map('strval', $settings['scheduleTargetOrder']));
            if (is_array($settings['titleExclusions'] ?? null)) $state['titleExclusions'] = array_values(array_map('strval', $settings['titleExclusions']));
            if (is_array($settings['publicCalendarSettings'] ?? null)) $state['publicCalendarSettings'] = PublicEventCalendar::normalizeSettings($settings['publicCalendarSettings']);
        }
        if (isset($sections['integrations']) && is_array($sections['integrations'])) {
            $integrations = $sections['integrations'];
            foreach (['crewbrain', 'easyjob'] as $provider) {
                if (is_array($integrations[$provider]['config'] ?? null)) {
                    $state[$provider]['config'] = array_replace($state[$provider]['config'] ?? [], $this->withoutSecrets($integrations[$provider]['config']));
                    $state[$provider]['config']['syncEnabled'] = false;
                }
                if (is_array($integrations[$provider]['mapping'] ?? null)) $state[$provider]['mapping'] = $integrations[$provider]['mapping'];
            }
            $state['activeEventSource'] = null;
        }
        $users = is_array($sections['users']['records'] ?? null) ? $sections['users']['records'] : [];
        $apiUsers = is_array($sections['api']['records'] ?? null) ? $sections['api']['records'] : [];
        $state['portableUsers'] = $this->uniqueBy(array_merge($state['portableUsers'] ?? [], $this->sanitizeUsers($users, $maps)), 'email');
        $state['portableApiUsers'] = $this->uniqueBy(array_merge($state['portableApiUsers'] ?? [], $this->sanitizeApiUsers($apiUsers)), 'name');
        return [
            'counts' => $counts + ['users' => count($users), 'apiUsers' => count($apiUsers)],
            'includedSections' => array_keys($sections),
            'requiresProvisioning' => [
                'users' => count($users),
                'apiUsers' => count($apiUsers),
                'integrations' => isset($sections['integrations']) ? ['crewbrain', 'easyjob'] : [],
            ],
            'notices' => [
                'Benutzerkonten werden ohne Passwörter als übertragbare Profile vorgemerkt.',
                'API-Schlüssel und Schnittstellen-Zugangsdaten werden aus Sicherheitsgründen nicht importiert.',
                'Aktive Warnungen werden als Entwurf übernommen.',
            ],
        ];
    }

    /** @param list<string> $requested @return list<string> */
    private function selection(array $requested): array
    {
        $selected = array_values(array_unique(array_filter(array_map('strval', $requested ?: self::SECTIONS), static fn(string $section): bool => in_array($section, self::SECTIONS, true))));
        $dependencies = ['channels' => ['slides'], 'displays' => ['channels', 'slides'], 'schedules' => ['displays', 'channels', 'slides'], 'operations' => ['displays', 'channels', 'slides']];
        do {
            $before = count($selected);
            foreach ($selected as $section) foreach ($dependencies[$section] ?? [] as $dependency) if (!in_array($dependency, $selected, true)) $selected[] = $dependency;
        } while ($before !== count($selected));
        return array_values(array_filter(self::SECTIONS, static fn(string $section): bool => in_array($section, $selected, true)));
    }

    /** @param list<array<string,mixed>> $records @param array<string,array<string,string>> $maps @return list<array<string,mixed>> */
    private function rewriteRecords(array $records, array $maps, string $collection): array
    {
        return array_values(array_map(function (array $record) use ($maps, $collection): array {
            $old = (string)($record['id'] ?? '');
            $record['id'] = $maps[$collection][$old] ?? $this->uuid();
            unset($record['deletedAt'], $record['deletedBy'], $record['archivedAt']);
            return $this->rewriteValue($record, $maps);
        }, array_filter($records, 'is_array')));
    }

    /** @param array<string,array<string,string>> $maps */
    private function rewriteValue(mixed $value, array $maps, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $rewritten = [];
            foreach ($value as $entryKey => $entry) $rewritten[$entryKey] = $this->rewriteValue($entry, $maps, is_string($entryKey) ? $entryKey : $key);
            return $rewritten;
        }
        if (!is_string($value)) return $value;
        $keys = [
            'eventId' => 'events', 'slideId' => 'slides', 'slideSourceId' => 'slides', 'channelId' => 'channels',
            'defaultChannelId' => 'channels', 'contentId' => null, 'locationId' => 'locations', 'displayId' => 'displays',
            'groupId' => 'groups', 'matrixId' => 'matrices', 'templateId' => 'templates', 'folderId' => 'mediaFolders',
            'parentId' => 'mediaFolders', 'mediaAssetId' => 'mediaAssets', 'presetId' => 'presets', 'providerId' => null,
        ];
        if ($key === 'targetId') {
            foreach (['displays', 'groups', 'matrices', 'locations'] as $collection) if (isset($maps[$collection][$value])) return $maps[$collection][$value];
        }
        if (($key === 'contentId' || $key === 'defaultChannelSourceId') && isset($maps['channels'][$value])) return $maps['channels'][$value];
        if ($key === 'contentId' && isset($maps['slides'][$value])) return $maps['slides'][$value];
        if ($key !== null && isset($keys[$key]) && $keys[$key] !== null) return $maps[$keys[$key]][$value] ?? $value;
        if ($key !== null && in_array($key, ['targets', 'targetKeys'], true) && preg_match('/^(display|group|matrix|location):(.+)$/', $value, $match)) {
            $collection = ['display' => 'displays', 'group' => 'groups', 'matrix' => 'matrices', 'location' => 'locations'][$match[1]];
            return $match[1] . ':' . ($maps[$collection][$match[2]] ?? $match[2]);
        }
        return $value;
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function active(array $records): array
    {
        return array_values(array_filter($records, static fn(mixed $record): bool => is_array($record) && empty($record['deletedAt']) && empty($record['archivedAt'])));
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function uniqueBy(array $records, string $key): array
    {
        $result = [];
        foreach ($records as $record) if (is_array($record) && trim((string)($record[$key] ?? '')) !== '') $result[strtolower((string)$record[$key])] = $record;
        return array_values($result);
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private function withoutSecrets(array $value): array
    {
        foreach (array_keys($value) as $key) {
            if (preg_match('/credential|password|secret|token|api.?key|authorization/i', (string)$key)) unset($value[$key]);
            elseif (is_array($value[$key])) $value[$key] = $this->withoutSecrets($value[$key]);
        }
        return $value;
    }

    /** @param list<array<string,mixed>> $users @param array<string,array<string,string>> $maps @return list<array<string,mixed>> */
    private function sanitizeUsers(array $users, array $maps): array
    {
        return array_values(array_filter(array_map(static function (mixed $user) use ($maps): ?array {
            if (!is_array($user) || !filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) return null;
            return [
                'email' => strtolower((string)$user['email']),
                'username' => (string)($user['username'] ?? strstr((string)$user['email'], '@', true)),
                'displayName' => (string)($user['displayName'] ?? $user['email']),
                'role' => in_array($user['role'] ?? '', ['admin', 'editor', 'viewer'], true) ? $user['role'] : 'viewer',
                'status' => ($user['status'] ?? '') === 'disabled' ? 'disabled' : 'active',
                'allLocations' => (bool)($user['allLocations'] ?? true),
                'locationIds' => is_array($user['locationIds'] ?? null) ? array_values(array_filter(array_map(
                    static fn(mixed $id): ?string => isset($maps['locations'][(string)$id]) ? $maps['locations'][(string)$id] : null,
                    $user['locationIds']
                ))) : [],
            ];
        }, $users)));
    }

    /** @param list<array<string,mixed>> $users @return list<array<string,mixed>> */
    private function sanitizeApiUsers(array $users): array
    {
        return array_values(array_filter(array_map(static function (mixed $user): ?array {
            if (!is_array($user) || trim((string)($user['name'] ?? '')) === '') return null;
            return ['name' => (string)$user['name'], 'description' => (string)($user['description'] ?? ''), 'status' => ($user['status'] ?? '') === 'revoked' ? 'revoked' : 'active'];
        }, $users)));
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
