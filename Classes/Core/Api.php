<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;
use Throwable;

final class Api
{
    /** @param array{id:string,name:string,email:string,role:string} $identity */
    public function __construct(
        private readonly StateStore $store,
        private readonly array $identity,
        private readonly string $version,
        private readonly ?CrewBrainService $crewBrain = null,
        private readonly ?DwdService $dwd = null,
        private readonly ?EasyJobService $easyJob = null,
        /** @var list<array<string,mixed>> */
        private readonly array $platformUsers = [],
        private readonly string $platform = 'cms',
    ) {
    }

    /** @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>} */
    public function handle(string $method, string $path, array $body = [], string $playerKey = ''): array
    {
        try {
            $method = strtoupper($method);
            $query = [];
            parse_str((string)(parse_url($path, PHP_URL_QUERY) ?: ''), $query);
            $body = array_merge($query, $body);
            $path = '/' . ltrim((string)(parse_url($path, PHP_URL_PATH) ?: ''), '/');
            $state = $this->state();
            $result = $this->route($state, $method, $path, $body, $playerKey);
            if ($result[2]) {
                $state['updatedAt'] = $this->now();
                $this->store->save($state);
            }
            return [$result[0], $result[1]];
        } catch (Throwable $error) {
            $status = $error instanceof RuntimeException ? max(400, min(599, $error->getCode() ?: 422)) : 500;
            return [$status, ['error' => [
                'code' => $status === 404 ? 'NOT_FOUND' : ($status === 403 ? 'FORBIDDEN' : 'INVALID_REQUEST'),
                'message' => $error->getMessage() ?: 'Die Anfrage konnte nicht verarbeitet werden.',
            ]]];
        }
    }

    /** @return array<string,mixed> */
    public function runScheduledTasks(): array
    {
        $state = $this->state();
        $summary = ($state['activeEventSource'] ?? null) === 'easyjob'
            ? $this->runAutomaticEasyJobImport($state)
            : $this->runAutomaticCrewBrainImport($state);
        if ($summary['ran']) {
            $state['updatedAt'] = $this->now();
            $this->store->save($state);
        }
        return $summary;
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>,2:bool} */
    private function route(array &$state, string $method, string $path, array $body, string $playerKey): array
    {
        if ($path === '/api/v1' || str_starts_with($path, '/api/v1/')) {
            $this->assertRolePermission($method, $path);
            return $this->externalApi($state, $method, $path, $body);
        }
        if ($path === '/api/health' && $method === 'GET') {
            return $this->ok(['status' => 'ok', 'version' => $this->version, 'buildNumber' => $this->version . '-cms', 'database' => 'cms']);
        }
        if ($path === '/api/auth/setup-status' && $method === 'GET') return $this->ok(['needsSetup' => false]);
        if ($path === '/api/auth/me' && $method === 'GET') return $this->ok(['user' => $this->user()]);
        if ($path === '/api/auth/logout' && $method === 'POST') return $this->ok(['ok' => true]);
        if ($path === '/api/auth/password' && $method === 'POST') throw new RuntimeException('Passwörter werden vom CMS verwaltet.', 409);
        if ($path === '/api/public/calendar' && $method === 'GET') {
            return $this->ok([
                'data' => PublicEventCalendar::events($state),
                'settings' => PublicEventCalendar::normalizeSettings($state['publicCalendarSettings'] ?? []),
            ]);
        }
        if ($path === '/api/users' && $method === 'GET') return $this->ok(['data' => [$this->user()]]);
        if ($path === '/api/users/invitations' && $method === 'GET') return $this->ok(['data' => []]);
        if ($path === '/api/api-users' && $method === 'GET') return $this->ok(['data' => []]);
        if ($path === '/api/audit-logs' && $method === 'GET') return $this->ok(['data' => array_reverse($state['audit'])]);
        if ($path === '/api/player-pairings' && $method === 'POST') {
            $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            do {
                $code = '';
                for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            } while ($this->first($state['playerPairings'], fn(array $item): bool => ($item['code'] ?? '') === $code && strtotime((string)($item['expiresAt'] ?? '')) > time()));
            $pairing = $this->record([
                'code' => $code, 'secret' => bin2hex(random_bytes(24)), 'status' => 'pending',
                'platform' => (string)($body['platform'] ?? 'Universal Player'),
                'playerBaseUrl' => (string)($body['playerBaseUrl'] ?? ''), 'expiresAt' => gmdate('c', time() + 900),
            ]);
            $state['playerPairings'][] = $pairing;
            return $this->created(['id' => $pairing['id'], 'code' => $code, 'secret' => $pairing['secret'], 'status' => 'pending', 'expiresAt' => $pairing['expiresAt']]);
        }
        if (preg_match('#^/api/player-pairings/([^/]+)$#', $path, $match) && $method === 'GET') {
            $pairing = $this->required($state['playerPairings'], rawurldecode($match[1]), 'Kopplung');
            if (!hash_equals((string)$pairing['secret'], $playerKey)) throw new RuntimeException('Kopplung wurde nicht gefunden.', 404);
            if (strtotime((string)$pairing['expiresAt']) <= time()) return $this->ok(['id' => $pairing['id'], 'code' => $pairing['code'], 'status' => 'expired']);
            if (($pairing['status'] ?? '') !== 'paired') return $this->ok(['id' => $pairing['id'], 'code' => $pairing['code'], 'status' => 'pending']);
            $display = $this->required($state['displays'], (string)$pairing['displayId'], 'Display');
            $base = rtrim((string)($pairing['playerBaseUrl'] ?? ''), '/') . '/';
            return $this->ok(['id' => $pairing['id'], 'code' => $pairing['code'], 'status' => 'paired', 'display' => ['id' => $display['id'], 'name' => $display['name']], 'playerUrl' => $base . '?display=' . rawurlencode((string)$display['slug']) . '&key=' . rawurlencode((string)$display['playerKey'])]);
        }

        if (preg_match('#^/api/player/([^/]+)/(state|heartbeat|proof)$#', $path, $match)) {
            return $this->player($state, $method, rawurldecode($match[1]), $match[2], $body, $playerKey);
        }

        $this->assertRolePermission($method, $path);

        if ($path === '/api/content-transfer/export' && in_array($method, ['GET', 'POST'], true)) {
            $requested = is_array($body['sections'] ?? null) ? array_values(array_map('strval', $body['sections'])) : [];
            $package = (new TransferService())->export($state, $requested, $this->platform, $this->version, $this->platformUsers);
            $this->audit($state, 'content_transfer.exported', implode(',', $package['includedSections']));
            return $this->ok($package, true);
        }
        if ($path === '/api/content-transfer/import' && $method === 'POST') {
            $result = (new TransferService())->import($state, $body);
            $this->audit($state, 'content_transfer.imported', implode(',', $result['includedSections']));
            return $this->ok(['imported' => $result], true);
        }

        if ($path === '/api/events') {
            if ($method === 'GET') return $this->ok(['data' => $this->active($state['events'])]);
            if ($method === 'POST') {
                $event = $this->saveRecord($state, 'events', $body);
                $this->ensureEventChannel($state, $event);
                return $this->created(['event' => $event]);
            }
        }
        if ($path === '/api/events/bulk' && $method === 'POST') {
            $ids = array_values(array_unique(array_map('strval', is_array($body['ids'] ?? null) ? $body['ids'] : [])));
            $action = (string)($body['action'] ?? '');
            if (!$ids) throw new RuntimeException('Bitte mindestens eine Veranstaltung auswählen.', 422);
            if ($action === 'delete' && ($this->identity['role'] ?? '') !== 'admin') {
                throw new RuntimeException('Endgültiges Löschen ist Administratoren vorbehalten.', 403);
            }
            if ($action === 'update') {
                $field = (string)($body['field'] ?? '');
                if (!in_array($field, ['admissionStart', 'eventStart', 'breakStart', 'eventEnd', 'imageUrl'], true)) throw new RuntimeException('Das Feld der Massenaktion ist ungültig.', 422);
                $time = (string)($body['time'] ?? ''); $breakEndTime = (string)($body['breakEndTime'] ?? ''); $imageUrl = trim((string)($body['imageUrl'] ?? ''));
                if ($field === 'imageUrl' && ($imageUrl === '' || strlen($imageUrl) > 2000)) throw new RuntimeException('Bitte ein Veranstaltungsbild auswählen.', 422);
                if ($field !== 'imageUrl' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) throw new RuntimeException('Bitte eine gültige Uhrzeit auswählen.', 422);
                if ($breakEndTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $breakEndTime)) throw new RuntimeException('Das Ende der Pause ist ungültig.', 422);
                $result = ['updated' => 0, 'skippedManual' => 0, 'skippedWithoutDate' => 0, 'missing' => 0];
                foreach ($ids as $id) {
                    $index = $this->index($state['events'], $id); if ($index === null) { $result['missing']++; continue; }
                    $event = $state['events'][$index]; if (empty($event['sourceId'])) { $result['skippedManual']++; continue; }
                    $locks = is_array($event['lockedFields'] ?? null) ? $event['lockedFields'] : [];
                    if ($field === 'imageUrl') $event['imageUrl'] = $imageUrl;
                    else {
                        $value = $this->berlinEventTime((string)($event['date'] ?? ''), $time); if ($value === null) { $result['skippedWithoutDate']++; continue; }
                        $event[$field] = $value;
                        if ($field === 'breakStart' && $breakEndTime !== '') { $event['breakEnd'] = $this->berlinEventTime((string)($event['date'] ?? ''), $breakEndTime); $locks[] = 'breakEnd'; }
                    }
                    $locks[] = $field; $event['lockedFields'] = array_values(array_unique($locks)); $event['syncStatus'] = 'manually_modified';
                    $this->saveRecord($state, 'events', $event, $id); $result['updated']++;
                }
                return $this->ok(['ok' => true, 'count' => $result['updated']] + $result, true);
            }
            foreach ($ids as $id) {
                if ($action === 'archive') $this->trashRecord($state, 'events', 'event', $id);
                elseif ($action === 'delete') $this->purgeEvent($state, $id);
                else throw new RuntimeException('Unbekannte Sammelaktion.', 422);
            }
            return $this->ok(['ok' => true, 'count' => count($ids)], true);
        }
        if (preg_match('#^/api/events/([^/]+)$#', $path, $match)) {
            $id = rawurldecode($match[1]);
            if ($method === 'GET') return $this->ok(['event' => $this->required($state['events'], $id, 'Veranstaltung')]);
            if ($method === 'PUT') {
                $current = $this->required($state['events'], $id, 'Veranstaltung');
                if (($current['sourceId'] ?? '') === 'source-crewbrain') {
                    $locked = array_values(array_unique(array_merge(
                        is_array($current['lockedFields'] ?? null) ? $current['lockedFields'] : [],
                        array_keys(array_filter($body, static fn(mixed $value, string $key): bool => !in_array($key, ['id', 'sourceId', 'externalId', 'externalObjectType', 'importHash', 'lastSyncedAt', 'syncStatus'], true) && ($current[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH))
                    )));
                    $body['lockedFields'] = $locked;
                }
                return $this->ok(['event' => $this->saveRecord($state, 'events', $body, $id)], true);
            }
            if ($method === 'DELETE') return $this->trashRecord($state, 'events', 'event', $id);
        }
        if (preg_match('#^/api/events/([^/]+)/usage$#', $path, $match) && $method === 'GET') {
            return $this->ok($this->eventUsage($state, rawurldecode($match[1])));
        }
        if (preg_match('#^/api/events/([^/]+)/permanent$#', $path, $match) && $method === 'DELETE') {
            if (($this->identity['role'] ?? '') !== 'admin') throw new RuntimeException('Endgültiges Löschen ist Administratoren vorbehalten.', 403);
            $this->purgeEvent($state, rawurldecode($match[1]));
            return $this->ok(['ok' => true], true);
        }

        if ($path === '/api/slides') {
            if ($method === 'GET') return $this->ok(['data' => $this->active($state['slides'])]);
            if ($method === 'POST') {
                $slide = $this->saveSlide($state, $body);
                return $this->created(['slide' => $slide]);
            }
        }
        if (preg_match('#^/api/slides/([^/]+)/versions$#', $path, $match) && $method === 'GET') {
            return $this->ok(['data' => array_values(array_filter($state['slideVersions'], fn(array $v): bool => $v['slideId'] === rawurldecode($match[1])))]);
        }
        if (preg_match('#^/api/slides/([^/]+)/versions/(\d+)/restore$#', $path, $match) && $method === 'POST') {
            $id = rawurldecode($match[1]);
            $version = $this->first($state['slideVersions'], fn(array $v): bool => $v['slideId'] === $id && (int)$v['version'] === (int)$match[2]);
            if (!$version) throw new RuntimeException('Slide-Version wurde nicht gefunden.', 404);
            $current = $this->required($state['slides'], $id, 'Slide');
            return $this->ok(['slide' => $this->saveSlide($state, array_replace($current, ['document' => $version['document']]), $id)], true);
        }
        if (preg_match('#^/api/slides/([^/]+)/usage$#', $path, $match) && $method === 'GET') {
            $id = rawurldecode($match[1]);
            return $this->ok(['channels' => array_values(array_map(
                fn(array $channel): array => ['id' => $channel['id'], 'name' => $channel['name']],
                array_filter($this->active($state['channels']), fn(array $channel): bool => in_array($id, array_column($channel['items'] ?? [], 'slideId'), true))
            ))]);
        }
        if (preg_match('#^/api/slides/([^/]+)/duplicate$#', $path, $match) && $method === 'POST') {
            $source = $this->required($state['slides'], rawurldecode($match[1]), 'Slide');
            unset($source['id'], $source['createdAt'], $source['updatedAt'], $source['currentVersion']);
            $source['name'] .= ' (Kopie)';
            $source['status'] = 'draft';
            return $this->created(['slide' => $this->saveSlide($state, $source)]);
        }
        if (preg_match('#^/api/slides/([^/]+)(?:/permanent)?$#', $path, $match)) {
            $id = rawurldecode($match[1]);
            if ($method === 'PUT') return $this->ok(['slide' => $this->saveSlide($state, $body, $id)], true);
            if ($method === 'DELETE') {
                if (str_ends_with($path, '/permanent')) return $this->purgeRecord($state, 'slides', $id);
                return $this->trashRecord($state, 'slides', 'slide', $id);
            }
        }

        if ($path === '/api/channels') {
            if ($method === 'GET') return $this->ok(['data' => $this->active($state['channels'])]);
            if ($method === 'POST') return $this->created(['channel' => $this->saveRecord($state, 'channels', $body + ['items' => []])]);
        }
        if (preg_match('#^/api/channels/([^/]+)/usage$#', $path, $match) && $method === 'GET') {
            $id = rawurldecode($match[1]);
            return $this->ok([
                'schedules' => array_values(array_filter($state['schedules'], fn(array $item): bool => ($item['channelId'] ?? '') === $id)),
                'displays' => array_values(array_map(fn(array $item): array => ['id' => $item['id'], 'name' => $item['name']], array_filter($this->active($state['displays']), fn(array $item): bool => ($item['defaultChannelId'] ?? '') === $id))),
            ]);
        }
        if (preg_match('#^/api/channels/([^/]+)/duplicate$#', $path, $match) && $method === 'POST') {
            $source = $this->required($state['channels'], rawurldecode($match[1]), 'Kanal');
            unset($source['id'], $source['createdAt'], $source['updatedAt']);
            $source['name'] .= ' (Kopie)';
            $source['status'] = 'draft';
            return $this->created(['channel' => $this->saveRecord($state, 'channels', $source)]);
        }
        if (preg_match('#^/api/channels/([^/]+)(?:/permanent)?$#', $path, $match)) {
            $id = rawurldecode($match[1]);
            if ($method === 'PUT') return $this->ok(['channel' => $this->saveRecord($state, 'channels', $body, $id)], true);
            if ($method === 'DELETE') {
                if (str_ends_with($path, '/permanent')) return $this->purgeRecord($state, 'channels', $id);
                return $this->trashRecord($state, 'channels', 'channel', $id);
            }
        }

        if ($path === '/api/templates') return $this->collectionRoute($state, $method, 'templates', 'template', $body);
        if (preg_match('#^/api/templates/([^/]+)$#', $path, $match)) return $this->itemRoute($state, $method, 'templates', 'template', rawurldecode($match[1]), $body);

        if ($path === '/api/media' && $method === 'GET') return $this->ok(['folders' => $this->active($state['mediaFolders']), 'assets' => $state['mediaAssets']]);
        if ($path === '/api/media/folders' && $method === 'POST') return $this->created(['folder' => $this->saveRecord($state, 'mediaFolders', $body)]);
        if (preg_match('#^/api/media/folders/([^/]+)$#', $path, $match)) return $this->itemRoute($state, $method, 'mediaFolders', 'folder', rawurldecode($match[1]), $body, false);
        if ($path === '/api/media/assets' && $method === 'POST') return $this->created(['asset' => $this->saveRecord($state, 'mediaAssets', $body)]);
        if (preg_match('#^/api/media/assets/([^/]+)/(restore|permanent)$#', $path, $match)) {
            $id = rawurldecode($match[1]);
            if ($match[2] === 'restore' && $method === 'POST') return $this->restoreRecord($state, 'mediaAssets', $id);
            if ($match[2] === 'permanent' && $method === 'DELETE') return $this->purgeRecord($state, 'mediaAssets', $id);
        }
        if (preg_match('#^/api/media/assets/([^/]+)$#', $path, $match)) {
            $id = rawurldecode($match[1]);
            if ($method === 'PUT') return $this->ok(['asset' => $this->saveRecord($state, 'mediaAssets', $body, $id)], true);
            if ($method === 'DELETE') return $this->trashRecord($state, 'mediaAssets', 'media', $id);
        }
        if (preg_match('#^/api/displays/([^/]+)/pair$#', $path, $match) && $method === 'POST') {
            $display = $this->required($state['displays'], rawurldecode($match[1]), 'Display');
            $code = strtoupper(trim((string)($body['code'] ?? '')));
            $pairing = $this->first($state['playerPairings'], fn(array $item): bool => ($item['code'] ?? '') === $code && ($item['status'] ?? '') === 'pending' && strtotime((string)($item['expiresAt'] ?? '')) > time());
            if (!$pairing) throw new RuntimeException('Der Kopplungscode ist ungültig oder abgelaufen.', 404);
            $index = $this->index($state['playerPairings'], (string)$pairing['id']);
            $state['playerPairings'][$index]['status'] = 'paired';
            $state['playerPairings'][$index]['displayId'] = $display['id'];
            $state['playerPairings'][$index]['pairedAt'] = $this->now();
            return $this->ok(['pairing' => ['id' => $pairing['id'], 'status' => 'paired']], true);
        }

        foreach ([
            '/api/presets' => ['presets', 'preset'],
            '/api/displays' => ['displays', 'display'],
            '/api/display-groups' => ['groups', 'group'],
            '/api/locations' => ['locations', 'location'],
            '/api/matrix-displays' => ['matrices', 'matrix'],
            '/api/schedules' => ['schedules', 'entry'],
            '/api/warning-templates' => ['warningTemplates', 'template'],
            '/api/warnings' => ['warnings', 'warning'],
        ] as $route => [$collection, $singular]) {
            if ($path === $route) return $this->collectionRoute($state, $method, $collection, $singular, $body);
        }

        if (preg_match('#^/api/matrix-displays/([^/]+)/duplicate$#', $path, $match) && $method === 'POST') {
            $source = $this->required($state['matrices'], rawurldecode($match[1]), 'Matrix');
            unset($source['id'], $source['createdAt'], $source['updatedAt']);
            $source['name'] .= ' (Kopie)';
            $source['status'] = 'disabled';
            return $this->created(['matrix' => $this->saveRecord($state, 'matrices', $source)]);
        }
        if (preg_match('#^/api/(presets|displays|display-groups|locations|matrix-displays|schedules|warning-templates)/([^/]+)$#', $path, $match)) {
            $map = [
                'presets' => ['presets', 'preset', false],
                'displays' => ['displays', 'display', true],
                'display-groups' => ['groups', 'group', true],
                'locations' => ['locations', 'location', true],
                'matrix-displays' => ['matrices', 'matrix', true],
                'schedules' => ['schedules', 'entry', false],
                'warning-templates' => ['warningTemplates', 'template', false],
            ];
            [$collection, $singular, $trash] = $map[$match[1]];
            return $this->itemRoute($state, $method, $collection, $singular, rawurldecode($match[2]), $body, $trash);
        }

        if ($path === '/api/schedule-target-order') {
            if ($method === 'GET') return $this->ok(['data' => $state['scheduleTargetOrder']]);
            if ($method === 'PUT') {
                $state['scheduleTargetOrder'] = array_values(array_map('strval', $body['targetKeys'] ?? []));
                return $this->ok(['data' => $state['scheduleTargetOrder']], true);
            }
        }
        if ($path === '/api/schedules/publish' && $method === 'POST') {
            foreach ($state['schedules'] as &$schedule) $schedule['status'] = 'published';
            unset($schedule);
            return $this->ok(['count' => count($state['schedules'])], true);
        }
        if ($path === '/api/trash' && $method === 'GET') return $this->ok(['data' => $state['trash']]);
        if (preg_match('#^/api/trash/([^/]+)/([^/]+)$#', $path, $match)) {
            $type = rawurldecode($match[1]);
            $id = rawurldecode($match[2]);
            $collection = ['event' => 'events', 'slide' => 'slides', 'channel' => 'channels', 'display' => 'displays', 'display_group' => 'groups', 'location' => 'locations', 'matrix' => 'matrices', 'media' => 'mediaAssets'][$type] ?? '';
            if (!$collection) throw new RuntimeException('Unbekannter Papierkorbtyp.');
            if ($method === 'POST') return $this->restoreTrash($state, $collection, $type, $id);
            if ($method === 'DELETE') return $this->purgeTrash($state, $collection, $type, $id);
        }

        if (preg_match('#^/api/warnings/([^/]+)/(publish|end)$#', $path, $match) && $method === 'POST') {
            $id = rawurldecode($match[1]);
            $warning = $this->required($state['warnings'], $id, 'Warnhinweis');
            $warning['status'] = $match[2] === 'publish' ? 'active' : 'ended';
            $warning[$match[2] === 'publish' ? 'publishedAt' : 'endedAt'] = $this->now();
            $warning = $this->saveRecord($state, 'warnings', $warning, $id);
            return $this->ok($match[2] === 'publish' ? ['warning' => $warning] : ['ok' => true], true);
        }
        if (preg_match('#^/api/operations/displays/([^/]+)/commands$#', $path, $match) && $method === 'POST') {
            $command = $this->record(['displayId' => rawurldecode($match[1]), 'command' => (string)($body['command'] ?? 'sync')]);
            $state['commands'][] = $command;
            return $this->created(['command' => $command]);
        }
        if (preg_match('#^/api/presets/([^/]+)/execute$#', $path, $match) && $method === 'POST') {
            $preset = $this->required($state['presets'], rawurldecode($match[1]), 'Preset');
            $config = is_array($preset['config'] ?? null) ? $preset['config'] : [];
            if (($preset['type'] ?? '') !== 'emergency') {
                $channelUpdates = is_array($config['channelUpdates'] ?? null) ? $config['channelUpdates'] : [];
                $assignmentInputs = is_array($config['assignments'] ?? null) ? $config['assignments'] : [];
                if (!$channelUpdates && !$assignmentInputs) throw new RuntimeException('Das Preset enthält noch keine ausführbaren Zustände.', 422);
                $updatedChannels = [];
                foreach ($channelUpdates as $update) {
                    if (!is_array($update)) continue;
                    $channel = $this->required($state['channels'], (string)($update['channelId'] ?? ''), 'Kanal');
                    $slideIds = array_values(array_unique(array_map('strval', is_array($update['slideIds'] ?? null) ? $update['slideIds'] : [])));
                    if (!$slideIds) throw new RuntimeException('Ein Kanalzustand enthält keine Slides.', 422);
                    foreach ($slideIds as $slideId) $this->required($state['slides'], $slideId, 'Slide');
                    $channel['items'] = array_map(static fn(string $slideId): array => ['slideId' => $slideId, 'durationSeconds' => 12, 'transition' => 'inherit'], $slideIds);
                    $updatedChannels[] = $this->saveRecord($state, 'channels', $channel, (string)$channel['id']);
                }
                $assignments = [];
                foreach ($assignmentInputs as $input) {
                    if (!is_array($input)) continue;
                    $assignments[] = $this->saveAssignment($state, ['target' => ['type' => $input['targetType'] ?? '', 'id' => $input['targetId'] ?? ''], 'content' => ['type' => $input['contentType'] ?? '', 'id' => $input['contentId'] ?? ''], 'priority' => 500]);
                }
                $execution = $this->record(['presetId' => $preset['id'], 'targets' => array_map(static fn(array $item): array => $item['target'], $assignments), 'result' => ['action' => 'state_applied', 'channelUpdates' => $updatedChannels, 'assignments' => $assignments], 'executedAt' => $this->now()]);
                $state['presetExecutions'][] = $execution;
                return $this->ok(['result' => $execution['result']], true);
            }
            $targets = !empty($body['targets']) ? array_values(array_map('strval', $body['targets'])) : array_values(array_map('strval', $config['targets'] ?? []));
            if (!$targets) throw new RuntimeException('Das Preset enthält keine Zielbereiche.');
            $warning = $this->saveRecord($state, 'warnings', [
                'source' => 'manual', 'warningType' => $config['warningType'] ?? 'info',
                'severity' => (int)($config['severity'] ?? 3), 'title' => $config['title'] ?? $preset['name'],
                'description' => $config['description'] ?? '', 'instructions' => $config['instructions'] ?? '',
                'priority' => (int)($config['priority'] ?? 900), 'targets' => $targets,
                'document' => $config['document'] ?? [], 'returnBehavior' => $config['returnBehavior'] ?? 'resume_current_schedule',
                'status' => 'active', 'publishedAt' => $this->now(),
            ]);
            return $this->created(['warning' => $warning]);
        }
        if ($path === '/api/operations' && $method === 'GET') {
            $emergency = $this->first(array_reverse($state['warnings']), fn(array $item): bool => ($item['status'] ?? '') === 'active' && ($item['source'] ?? '') === 'legacy-emergency');
            return $this->ok(['displays' => $this->listCollection($state, 'displays'), 'emergency' => $emergency, 'warnings' => $state['warnings']]);
        }
        if ($path === '/api/operations/proof' && $method === 'GET') throw new RuntimeException('Proof of Play ist deaktiviert.', 410);
        if ($path === '/api/operations/emergency' && $method === 'POST') {
            $warning = $this->saveRecord($state, 'warnings', $body + [
                'source' => 'legacy-emergency',
                'warningType' => $body['type'] ?? 'emergency',
                'title' => $body['title'] ?? 'Warnhinweis',
                'description' => $body['message'] ?? '',
                'instructions' => $body['message'] ?? '',
                'severity' => 5,
                'priority' => 1000,
                'targets' => $body['targets'] ?? ['global'],
                'status' => 'active',
                'publishedAt' => $this->now(),
            ]);
            return $this->created(['alert' => $warning]);
        }
        if ($path === '/api/operations/emergency' && $method === 'DELETE') {
            foreach ($state['warnings'] as &$warning) {
                if (($warning['source'] ?? '') === 'legacy-emergency' && ($warning['status'] ?? '') === 'active') {
                    $warning['status'] = 'ended';
                    $warning['endedAt'] = $this->now();
                }
            }
            unset($warning);
            return $this->ok(['ok' => true], true);
        }

        if (preg_match('#^/api/dwd/config/([^/]+)$#', $path, $match)) {
            $locationId = rawurldecode($match[1]);
            if ($method === 'GET') return $this->ok(['configuration' => $state['dwd'][$locationId] ?? ['locationId' => $locationId, 'enabled' => true, 'confirmationRequired' => true, 'minSeverity' => 2, 'eventTypes' => [], 'targets' => ['location:' . $locationId]]]);
            if ($method === 'PUT') {
                $state['dwd'][$locationId] = array_replace($state['dwd'][$locationId] ?? [], $body, ['locationId' => $locationId, 'id' => (string)($state['dwd'][$locationId]['id'] ?? $this->uuid()), 'updatedAt' => $this->now()]);
                return $this->ok(['configuration' => $state['dwd'][$locationId]], true);
            }
        }
        if (preg_match('#^/api/dwd/(warnings|sync)/([^/]+)$#', $path, $match)) {
            if (!$this->dwd) throw new RuntimeException('Der DWD-HTTP-Dienst ist in dieser CMS-Ausgabe nicht verfügbar.', 503);
            $locationId = rawurldecode($match[2]);
            $location = $this->required($state['locations'], $locationId, 'Standort');
            $warnings = $this->dwd->warnings((string)($location['warningAreaCode'] ?? ''));
            $configuration = $state['dwd'][$locationId] ?? ['locationId' => $locationId, 'enabled' => true, 'confirmationRequired' => true, 'minSeverity' => 2, 'eventTypes' => [], 'targets' => ['location:' . $locationId]];
            $warnings = array_values(array_filter($warnings, static function (array $warning) use ($configuration): bool {
                if ((int)($warning['severity'] ?? 0) < (int)($configuration['minSeverity'] ?? 2)) return false;
                $types = is_array($configuration['eventTypes'] ?? null) ? array_filter(array_map('strval', $configuration['eventTypes'])) : [];
                return !$types || in_array((string)($warning['warningType'] ?? ''), $types, true);
            }));
            if ($match[1] === 'warnings' && $method === 'GET') return $this->ok(['data' => $warnings]);
            if ($match[1] === 'sync' && $method === 'POST') {
                $saved = [];
                foreach ($warnings as $warning) {
                    $existing = $this->first($state['warnings'], fn(array $item): bool => ($item['source'] ?? '') === 'dwd' && ($item['externalId'] ?? '') === ($warning['externalId'] ?? ''));
                    $warning = array_replace($existing ?? [], $warning, [
                        'locationId' => $locationId,
                        'priority' => 900,
                        'targets' => $configuration['targets'] ?? ['location:' . $locationId],
                        'returnBehavior' => 'resume_current_schedule',
                        'status' => empty($configuration['confirmationRequired']) ? 'active' : 'pending',
                        ...((empty($configuration['confirmationRequired'])) ? ['publishedAt' => $this->now()] : []),
                    ]);
                    $saved[] = $this->saveRecord($state, 'warnings', $warning, $existing['id'] ?? null);
                }
                $state['dwd'][$locationId] = array_replace($configuration, ['lastCheckedAt' => $this->now()]);
                $this->audit($state, 'dwd.warnings.synced', $locationId);
                return $this->ok(['data' => $saved, 'fetched' => count($warnings), 'autoPublished' => empty($configuration['confirmationRequired'])], true);
            }
            throw new RuntimeException('Methode nicht erlaubt.', 405);
        }

        if ($path === '/api/integrations/crewbrain' && $method === 'GET') {
            $service = $this->crewBrain();
            return $this->ok($service->publicConfig($state['crewbrain']['config']));
        }
        if ($path === '/api/integrations/crewbrain' && $method === 'PUT') {
            $service = $this->crewBrain();
            $state['crewbrain']['config'] = $service->saveConfig($state['crewbrain']['config'], $body);
            $this->audit($state, 'crewbrain.connection.updated', (string)$state['crewbrain']['config']['id']);
            return $this->ok($service->publicConfig($state['crewbrain']['config']), true);
        }
        if ($path === '/api/integrations/crewbrain/configuration-export' && $method === 'GET') {
            $service = $this->crewBrain();
            $export = $service->exportConfiguration($state['crewbrain']['config'], $state['crewbrain']['mapping']);
            $this->audit($state, 'crewbrain.configuration.exported', (string)($state['crewbrain']['config']['id'] ?? 'crewbrain'));
            return $this->ok($export, true);
        }
        if ($path === '/api/integrations/crewbrain/configuration-import' && $method === 'POST') {
            $service = $this->crewBrain();
            $imported = $service->importConfiguration($state['crewbrain']['config'], $body);
            $state['crewbrain']['config'] = $imported['config'];
            $state['crewbrain']['mapping'] = $imported['mapping'];
            $this->audit($state, 'crewbrain.configuration.imported', (string)$state['crewbrain']['config']['id']);
            return $this->ok([
                'config' => $service->publicConfig($state['crewbrain']['config']),
                'mapping' => $state['crewbrain']['mapping'],
            ], true);
        }
        if ($path === '/api/integrations/crewbrain/mapping' && $method === 'GET') return $this->ok($state['crewbrain']['mapping']);
        if ($path === '/api/integrations/crewbrain/mapping' && $method === 'PUT') {
            $state['crewbrain']['mapping'] = $this->crewBrain()->validateMapping($body);
            $this->audit($state, 'crewbrain.mapping.updated', 'mapping-crewbrain-default');
            return $this->ok($state['crewbrain']['mapping'], true);
        }
        if ($path === '/api/integrations/crewbrain/access-token' && $method === 'POST') {
            $service = $this->crewBrain();
            $token = $service->requestAccessToken($body);
            $state['crewbrain']['config'] = $service->saveConfig($state['crewbrain']['config'], array_replace($body, [
                'authType' => 'api_key',
                'credential' => ['apiKey' => $token],
            ]));
            unset($body['username'], $body['password']);
            $this->audit($state, 'crewbrain.access_token.created', (string)$state['crewbrain']['config']['id']);
            return $this->ok($service->publicConfig($state['crewbrain']['config']), true);
        }
        if ($path === '/api/integrations/crewbrain/test' && $method === 'POST') {
            $service = $this->crewBrain();
            $result = $service->test($state['crewbrain']['config']);
            $state['crewbrain']['config']['lastApiVersion'] = $result['apiVersion'] ?? 'API v2';
            $state['crewbrain']['config']['lastError'] = $result['ok'] ? null : ($result['error']['message'] ?? 'Verbindungstest fehlgeschlagen.');
            if ($result['ok']) $state['crewbrain']['config']['lastSuccessAt'] = $this->now();
            $this->audit($state, 'crewbrain.connection.tested', (string)($state['crewbrain']['config']['id'] ?? 'crewbrain'));
            return [$result['ok'] ? 200 : 422, $result, true];
        }
        if ($path === '/api/integrations/crewbrain/import-preview' && $method === 'POST') {
            $service = $this->crewBrain();
            $page = $service->listEvents($state['crewbrain']['config'], $body);
            $data = [];
            $excluded = 0;
            foreach ($page['data'] as $event) {
                $mapped = $service->mapEvent($event, $state['crewbrain']['mapping']);
                if ($service->titleExclusion($event, (string)$mapped['title'], $this->titleExclusions($state))) {
                    $excluded++;
                    continue;
                }
                $data[] = [
                    'crewbrain' => [
                        'id' => $event['ID'] ?? null,
                        'type' => $event['Type'] ?? null,
                        'subtype' => $event['Subtype'] ?? null,
                        'number' => $event['EventIDFormatted'] ?? $event['EventIDManual'] ?? null,
                        'changedAt' => $event['ChangedDate'] ?? null,
                    ],
                    'event' => $mapped,
                    'plannedAction' => 'review',
                ];
            }
            $this->audit($state, 'crewbrain.import.previewed', (string)($state['crewbrain']['config']['id'] ?? 'crewbrain'));
            return $this->ok(array_replace($page, ['data' => $data, 'itemCount' => count($data), 'excludedCount' => $excluded]), true);
        }
        if ($path === '/api/integrations/crewbrain/import' && $method === 'POST') {
            $ids = array_values(array_unique(array_slice(array_filter(
                array_map('strval', is_array($body['ids'] ?? null) ? $body['ids'] : []),
                static fn(string $id): bool => preg_match('/^\d+$/', $id) === 1
            ), 0, 100)));
            if (!$ids) throw new RuntimeException('Bitte mindestens eine Veranstaltung zum Import auswählen.', 422);
            $results = $this->importCrewBrainIds($state, $ids);
            $summary = [
                'created' => count(array_filter($results, fn(array $item): bool => $item['action'] === 'created')),
                'updated' => count(array_filter($results, fn(array $item): bool => $item['action'] === 'updated')),
                'unchanged' => count(array_filter($results, fn(array $item): bool => $item['action'] === 'unchanged')),
                'excluded' => count(array_filter($results, fn(array $item): bool => $item['action'] === 'excluded')),
                'errors' => count(array_filter($results, fn(array $item): bool => $item['action'] === 'error')),
            ];
            $this->audit($state, 'crewbrain.events.imported', (string)($state['crewbrain']['config']['id'] ?? 'crewbrain'));
            return [$summary['errors'] ? 207 : 200, ['summary' => $summary, 'results' => $results], true];
        }

        if ($path === '/api/integrations/sources' && $method === 'GET') {
            return $this->ok($this->integrationSources($state));
        }
        if ($path === '/api/integrations/sources' && $method === 'PUT') {
            $activeSource = $body['activeSource'] ?? null;
            if ($activeSource !== null && !in_array($activeSource, ['crewbrain', 'easyjob'], true)) throw new RuntimeException('Die ausgewählte Datenquelle ist ungültig.', 422);
            if ($activeSource === 'crewbrain' && empty($state['crewbrain']['config']['encryptedCredential'])) throw new RuntimeException('CrewBrain muss zuerst vollständig konfiguriert werden.', 422);
            if ($activeSource === 'easyjob' && empty($state['easyjob']['config']['encryptedCredentials'])) throw new RuntimeException('easyjob muss zuerst vollständig konfiguriert werden.', 422);
            $state['activeEventSource'] = $activeSource;
            $this->audit($state, 'integration.source.activated', $activeSource ?: 'none');
            return $this->ok($this->integrationSources($state), true);
        }
        if ($path === '/api/integrations/title-exclusions' && $method === 'GET') {
            return $this->ok(['titleExclusions' => $this->titleExclusions($state)]);
        }
        if ($path === '/api/integrations/title-exclusions' && $method === 'PUT') {
            $state['titleExclusions'] = $this->normalizeTitleExclusions($body['titleExclusions'] ?? []);
            $state['crewbrain']['config']['titleExclusions'] = $state['titleExclusions'];
            $this->audit($state, 'integration.title_exclusions.updated', 'title_exclusions');
            return $this->ok(['titleExclusions' => $state['titleExclusions']], true);
        }

        if ($path === '/api/integrations/easyjob' && $method === 'GET') return $this->ok($this->easyJob()->publicConfig($state['easyjob']['config']));
        if ($path === '/api/integrations/easyjob' && $method === 'PUT') {
            $state['easyjob']['config'] = $this->easyJob()->saveConfig($state['easyjob']['config'], $body);
            return $this->ok($this->easyJob()->publicConfig($state['easyjob']['config']), true);
        }
        if ($path === '/api/integrations/easyjob/mapping' && $method === 'GET') return $this->ok($state['easyjob']['mapping']);
        if ($path === '/api/integrations/easyjob/mapping' && $method === 'PUT') {
            $state['easyjob']['mapping'] = $this->easyJob()->validateMapping($body);
            $this->audit($state, 'easyjob.mapping.updated', 'mapping-easyjob-default');
            return $this->ok($state['easyjob']['mapping'], true);
        }
        if ($path === '/api/integrations/easyjob/test' && $method === 'POST') {
            $result = $this->easyJob()->test($state['easyjob']['config']);
            if ($result['ok']) $state['easyjob']['config']['lastSuccessAt'] = $this->now();
            return [$result['ok'] ? 200 : 422, $result, true];
        }
        if ($path === '/api/integrations/easyjob/import-preview' && $method === 'POST') {
            $projects = $this->easyJob()->projects($state['easyjob']['config'], ['searchtext' => $body['search'] ?? '', 'style' => 'Compact', 'startdate' => $body['from'] ?? '', 'enddate' => $body['until'] ?? '']);
            $data = [];
            $excluded = 0;
            foreach ($projects as $project) {
                $mapped = $this->easyJob()->mapEvent($project, null, $state['easyjob']['mapping']);
                if ($this->matchingTitleExclusion((string)($mapped['title'] ?? ''), $this->titleExclusions($state))) {
                    $excluded++;
                    continue;
                }
                $data[] = ['easyjob' => ['projectId' => $project['IdProject'] ?? $project['ID'] ?? $project['Id'] ?? '', 'number' => $project['Number'] ?? $project['CustomNumber'] ?? null], 'event' => $mapped, 'plannedAction' => 'review'];
            }
            return $this->ok(['data' => $data, 'itemCount' => count($data), 'totalItems' => count($projects), 'excludedCount' => $excluded], true);
        }
        if ($path === '/api/integrations/easyjob/import' && $method === 'POST') {
            $ids = array_slice(array_values(array_unique(array_map('strval', is_array($body['ids'] ?? null) ? $body['ids'] : []))), 0, 100);
            if (!$ids) throw new RuntimeException('Bitte mindestens ein easyjob-Projekt auswählen.', 422);
            $results = [];
            foreach ($ids as $projectId) {
                try {
                    $project = $this->easyJob()->project($state['easyjob']['config'], $projectId);
                    if (($state['easyjob']['config']['importMode'] ?? '') !== 'jobs') {
                        $mapped = $this->easyJob()->mapEvent($project, null, $state['easyjob']['mapping']);
                        $exclusion = $this->matchingTitleExclusion((string)($mapped['title'] ?? ''), $this->titleExclusions($state));
                        if ($exclusion) $results[] = ['action' => 'excluded', 'reason' => $exclusion, 'projectId' => $projectId];
                        else { $result = $this->upsertExternalEvent($state, $mapped); $result['projectId'] = $projectId; $result['images'] = $this->syncEasyJobAdvertisingImages($state, $result['event'], $projectId); $results[] = $result; }
                    }
                    if (($state['easyjob']['config']['importMode'] ?? '') !== 'projects') foreach (($project['Jobs'] ?? []) as $listedJob) {
                        if (!is_array($listedJob)) continue;
                        $jobId = (string)($listedJob['IdJob'] ?? $listedJob['ID'] ?? $listedJob['Id'] ?? '');
                        if ($jobId !== '') {
                            $mapped = $this->easyJob()->mapEvent($project, $this->easyJob()->job($state['easyjob']['config'], $jobId), $state['easyjob']['mapping']);
                            $exclusion = $this->matchingTitleExclusion((string)($mapped['title'] ?? ''), $this->titleExclusions($state));
                            if ($exclusion) $results[] = ['action' => 'excluded', 'reason' => $exclusion, 'projectId' => $projectId, 'jobId' => $jobId];
                            else { $result = $this->upsertExternalEvent($state, $mapped); $result['projectId'] = $projectId; $result['jobId'] = $jobId; $result['images'] = $this->syncEasyJobAdvertisingImages($state, $result['event'], $projectId, $jobId); $results[] = $result; }
                        }
                    }
                } catch (Throwable $error) { $results[] = ['action' => 'error', 'reason' => $error->getMessage(), 'projectId' => $projectId]; }
            }
            $summary = ['created' => count(array_filter($results, fn(array $item): bool => ($item['action'] ?? '') === 'created')), 'updated' => count(array_filter($results, fn(array $item): bool => ($item['action'] ?? '') === 'updated')), 'unchanged' => count(array_filter($results, fn(array $item): bool => ($item['action'] ?? '') === 'unchanged')), 'excluded' => count(array_filter($results, fn(array $item): bool => ($item['action'] ?? '') === 'excluded')), 'errors' => count(array_filter($results, fn(array $item): bool => ($item['action'] ?? '') === 'error')), 'imagesImported' => array_sum(array_map(fn(array $item): int => (int)($item['images']['imported'] ?? 0), $results)), 'imagesUpdated' => array_sum(array_map(fn(array $item): int => (int)($item['images']['updated'] ?? 0), $results)), 'imagesRemoved' => array_sum(array_map(fn(array $item): int => (int)($item['images']['removed'] ?? 0), $results)), 'imageWarnings' => array_sum(array_map(fn(array $item): int => count($item['images']['warnings'] ?? []), $results))];
            return [$summary['errors'] ? 207 : 200, ['summary' => $summary, 'results' => $results], true];
        }

        if ($path === '/api/settings/features' && $method === 'GET') return $this->ok(['data' => $state['featureSettings'], 'navigationOrder' => $this->normalizeNavigationOrder($state['navigationOrder'] ?? [])]);
        if ($path === '/api/settings/features' && $method === 'PUT') {
            $allowed = ['dashboard', 'events', 'displays', 'channels', 'media', 'schedule', 'operations', 'integrations', 'settings', 'users', 'content', 'presets_warnings'];
            foreach ($allowed as $key) if (is_bool($body[$key] ?? null)) $state['featureSettings'][$key] = $body[$key];
            $state['navigationOrder'] = $this->normalizeNavigationOrder($body['navigationOrder'] ?? $state['navigationOrder'] ?? []);
            return $this->ok(['data' => $state['featureSettings'], 'navigationOrder' => $state['navigationOrder']], true);
        }
        if ($path === '/api/settings/public-calendar' && $method === 'GET') {
            return $this->ok(['settings' => PublicEventCalendar::normalizeSettings($state['publicCalendarSettings'] ?? [])]);
        }
        if ($path === '/api/settings/public-calendar' && $method === 'PUT') {
            $state['publicCalendarSettings'] = PublicEventCalendar::normalizeSettings($body);
            return $this->ok(['settings' => $state['publicCalendarSettings']], true);
        }

        throw new RuntimeException('API-Endpunkt wurde nicht gefunden.', 404);
    }

    private function crewBrain(): CrewBrainService
    {
        if (!$this->crewBrain) throw new RuntimeException('Der CrewBrain-HTTP-Dienst ist in dieser CMS-Ausgabe nicht verfügbar.', 503);
        return $this->crewBrain;
    }

    private function easyJob(): EasyJobService
    {
        if (!$this->easyJob) throw new RuntimeException('Der easyjob-HTTP-Dienst ist in dieser CMS-Ausgabe nicht verfügbar.', 503);
        return $this->easyJob;
    }

    private function assertRolePermission(string $method, string $path): void
    {
        $role = (string)($this->identity['role'] ?? 'viewer');
        if ($role === 'admin') return;

        $adminOnly = ['/api/users', '/api/api-users', '/api/audit-logs', '/api/content-transfer', '/api/integrations', '/api/settings', '/api/v1'];
        if ($this->pathStartsWithAny($path, $adminOnly)) {
            throw new RuntimeException('Für diese Funktion fehlen die erforderlichen Rechte.', 403);
        }

        if ($role === 'editor') {
            if ($method === 'POST' && ($path === '/api/displays' || $path === '/api/matrix-displays' || preg_match('#^/api/matrix-displays/[^/]+/duplicate$#', $path))) {
                throw new RuntimeException('Neue Displays dürfen nur Administratoren anlegen.', 403);
            }
            if ($method === 'DELETE' && (str_ends_with($path, '/permanent') || str_starts_with($path, '/api/trash/'))) {
                throw new RuntimeException('Endgültiges Löschen ist Administratoren vorbehalten.', 403);
            }
            return;
        }

        $viewerBlocked = ['/api/media', '/api/presets', '/api/warning-templates', '/api/warnings', '/api/operations', '/api/dwd', '/api/trash'];
        if ($this->pathStartsWithAny($path, $viewerBlocked)) {
            throw new RuntimeException('Für diese Funktion fehlen die erforderlichen Rechte.', 403);
        }
        if (in_array($method, ['GET', 'HEAD'], true)) return;
        if ($method === 'DELETE') throw new RuntimeException('Betrachter dürfen keine Daten löschen oder archivieren.', 403);

        $allowed = ($method === 'POST' && in_array($path, ['/api/slides', '/api/channels', '/api/templates', '/api/schedules'], true))
            || ($method === 'PUT' && $path === '/api/schedule-target-order')
            || (preg_match('#^/api/slides/[^/]+(?:/duplicate|/versions/\d+/restore)?$#', $path) && in_array($method, ['PUT', 'POST'], true))
            || (preg_match('#^/api/channels/[^/]+(?:/duplicate)?$#', $path) && in_array($method, ['PUT', 'POST'], true))
            || ($method === 'PUT' && preg_match('#^/api/(templates|schedules)/[^/]+$#', $path));
        if (!$allowed) throw new RuntimeException('Für diese Funktion fehlen die erforderlichen Rechte.', 403);
    }

    /** @param array<int,string> $prefixes */
    private function pathStartsWithAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) return true;
        }
        return false;
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function eventUsage(array $state, string $eventId): array
    {
        $this->required($state['events'], $eventId, 'Veranstaltung');
        $slides = array_values(array_map(
            static fn(array $slide): array => ['id' => $slide['id'], 'name' => $slide['name'] ?? 'Slide'],
            array_filter($this->active($state['slides']), static fn(array $slide): bool => ($slide['eventId'] ?? '') === $eventId)
        ));
        $channels = array_values(array_map(
            static fn(array $channel): array => ['id' => $channel['id'], 'name' => $channel['name'] ?? 'Kanal'],
            array_filter($this->active($state['channels']), static fn(array $channel): bool => ($channel['eventId'] ?? '') === $eventId)
        ));
        return ['slides' => $slides, 'channels' => $channels];
    }

    /** @param array<string,mixed> $state */
    private function purgeEvent(array &$state, string $eventId): void
    {
        $this->required($state['events'], $eventId, 'Veranstaltung');
        foreach ($state['slides'] as &$slide) if (($slide['eventId'] ?? '') === $eventId) unset($slide['eventId']);
        unset($slide);
        foreach ($state['channels'] as &$channel) if (($channel['eventId'] ?? '') === $eventId) unset($channel['eventId']);
        unset($channel);
        $this->purge($state['events'], $eventId);
        $state['trash'] = array_values(array_filter($state['trash'], static fn(array $item): bool => !(($item['type'] ?? '') === 'event' && ($item['id'] ?? '') === $eventId)));
        $this->audit($state, 'event.purged', $eventId);
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $mapped
     * @return array{event:array<string,mixed>,action:string}
     */
    private function upsertCrewBrainEvent(array &$state, array $mapped): array
    {
        $existing = $this->first($state['events'], static fn(array $event): bool =>
            ($event['sourceId'] ?? '') === 'source-crewbrain'
            && (string)($event['externalId'] ?? '') === (string)($mapped['externalId'] ?? '')
            && (string)($event['externalObjectType'] ?? '') === (string)($mapped['externalObjectType'] ?? '')
        );
        if (!$existing) {
            $event = $this->saveRecord($state, 'events', $mapped + ['status' => 'review', 'lockedFields' => []]);
            $this->ensureEventChannel($state, $event);
            return ['event' => $event, 'action' => 'created'];
        }
        if (($existing['importHash'] ?? '') === ($mapped['importHash'] ?? '')) {
            return ['event' => $existing, 'action' => 'unchanged'];
        }
        $locked = is_array($existing['lockedFields'] ?? null) ? $existing['lockedFields'] : [];
        $updated = $existing;
        foreach ($mapped as $key => $value) {
            if (!in_array($key, $locked, true)) $updated[$key] = $value;
        }
        $updated['lockedFields'] = $locked;
        unset($updated['deletedAt']);
        $event = $this->saveRecord($state, 'events', $updated, (string)$existing['id']);
        $this->ensureEventChannel($state, $event);
        return ['event' => $event, 'action' => 'updated'];
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $mapped @return array{event:array<string,mixed>,action:string} */
    private function upsertExternalEvent(array &$state, array $mapped): array
    {
        $existing = $this->first($state['events'], static fn(array $event): bool => ($event['sourceId'] ?? '') === ($mapped['sourceId'] ?? '') && (string)($event['externalId'] ?? '') === (string)($mapped['externalId'] ?? ''));
        if (!$existing) {
            $event = $this->saveRecord($state, 'events', $mapped + ['status' => 'review', 'lockedFields' => []]);
            $this->ensureEventChannel($state, $event);
            return ['event' => $event, 'eventId' => $event['id'], 'action' => 'created'];
        }
        if (($existing['importHash'] ?? '') === ($mapped['importHash'] ?? '')) return ['event' => $existing, 'eventId' => $existing['id'], 'action' => 'unchanged'];
        $locked = is_array($existing['lockedFields'] ?? null) ? $existing['lockedFields'] : [];
        $updated = $existing;
        foreach ($mapped as $key => $value) if (!in_array($key, $locked, true)) $updated[$key] = $value;
        $updated['lockedFields'] = $locked;
        $updated['syncStatus'] = $locked ? 'manually_modified' : ($mapped['syncStatus'] ?? 'synced');
        if (isset($mapped['importHash'])) $updated['importHash'] = $mapped['importHash'];
        $event = $this->saveRecord($state, 'events', $updated, (string)$existing['id']);
        $this->ensureEventChannel($state, $event);
        return ['event' => $event, 'eventId' => $event['id'], 'action' => 'updated'];
    }

    /** @param array<string,mixed> $source */
    private function easyJobAttachmentText(array $source, array $paths): string
    {
        foreach ($paths as $path) {
            $value = $source;
            foreach (explode('.', $path) as $key) $value = is_array($value) ? ($value[$key] ?? null) : null;
            if (is_scalar($value) && trim((string)$value) !== '') return trim((string)$value);
        }
        return '';
    }

    /** @param array<string,mixed> $attachment */
    private function easyJobDocumentType(array $attachment): string
    {
        foreach (['DocumentType','ShortCutType','AttachmentType','Type'] as $key) {
            $value = $attachment[$key] ?? null;
            if (is_string($value)) return trim($value);
            if (is_array($value)) {
                $caption = $this->easyJobAttachmentText($value, ['Caption','CaptionNew','Name','Description','Value']);
                if ($caption !== '') return $caption;
            }
        }
        return $this->easyJobAttachmentText($attachment, ['DocumentTypeCaption','DocumentTypeName','TypeCaption','TypeName']);
    }

    /** @param array<string,mixed> $state */
    private function ensureEasyJobMediaFolder(array &$state, string $name, ?string $parentId = null): string
    {
        foreach ($state['mediaFolders'] as $folder) if (($folder['name'] ?? '') === $name && (string)($folder['parentId'] ?? '') === (string)($parentId ?? '')) return (string)$folder['id'];
        return (string)$this->saveRecord($state, 'mediaFolders', ['name' => $name, 'parentId' => $parentId])['id'];
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $event @return array<string,mixed> */
    private function syncEasyJobAdvertisingImages(array &$state, array $event, string $projectId, ?string $jobId = null): array
    {
        $service = $this->easyJob();
        $config = $state['easyjob']['config'];
        $warnings = [];
        $source = 'none';
        $attachments = [];
        if ($jobId) {
            try { $attachments = $service->shortcuts($config, $jobId, 'job'); }
            catch (Throwable $error) { $warnings[] = 'Job-Anhänge konnten nicht gelesen werden: ' . $error->getMessage(); }
            $attachments = array_values(array_filter($attachments, fn(array $item): bool => strtolower(trim($this->easyJobDocumentType($item))) === 'veranstaltungsbild'));
            if ($attachments) $source = 'job';
        }
        if (!$attachments) {
            try { $attachments = $service->shortcuts($config, $projectId, 'project'); }
            catch (Throwable $error) { return ['source' => 'none', 'found' => 0, 'imported' => 0, 'updated' => 0, 'removed' => 0, 'warnings' => array_merge($warnings, ['Projektanhänge konnten nicht gelesen werden: ' . $error->getMessage()])]; }
            $attachments = array_values(array_filter($attachments, fn(array $item): bool => strtolower(trim($this->easyJobDocumentType($item))) === 'veranstaltungsbild'));
            if ($attachments) $source = 'project';
        }
        usort($attachments, fn(array $left, array $right): int => strcmp((string)($right['ChangedDate'] ?? $right['UpdatedAt'] ?? ''), (string)($left['ChangedDate'] ?? $left['UpdatedAt'] ?? '')));
        $existing = array_values(array_filter($state['mediaAssets'], fn(array $asset): bool => ($asset['metadata']['source'] ?? '') === 'easyjob' && ($asset['metadata']['easyjobDocumentType'] ?? '') === 'Veranstaltungsbild' && ($asset['metadata']['easyjobEventId'] ?? '') === ($event['id'] ?? '')));
        if (!$attachments) {
            foreach ($existing as $asset) {
                $index = $this->index($state['mediaAssets'], (string)$asset['id']);
                if ($index !== null) $state['mediaAssets'][$index]['deletedAt'] = $this->now();
            }
            if (!in_array('imageUrl', $event['lockedFields'] ?? [], true) && $existing) {
                $event['imageUrl'] = null;
                $this->saveRecord($state, 'events', $event, (string)$event['id']);
            }
            return ['source' => $source, 'found' => 0, 'imported' => 0, 'updated' => 0, 'removed' => count($existing), 'warnings' => $warnings];
        }
        $date = preg_match('/^(\d{4})(\d{2})(\d{2})/', (string)($event['date'] ?? ''), $dateMatch) ? [$dateMatch[1],$dateMatch[2],$dateMatch[3]] : [date('Y'),date('m'),date('d')];
        $rootId = $this->ensureEasyJobMediaFolder($state, 'Werbung');
        $yearId = $this->ensureEasyJobMediaFolder($state, $date[0], $rootId);
        $monthId = $this->ensureEasyJobMediaFolder($state, $date[1], $yearId);
        $safeTitle = trim(preg_replace('/[\x00-\x1F\x7F\/\\:*?"<>|]+/u', ' ', (string)($event['title'] ?? $event['id'])) ?: (string)$event['id']);
        $shortTitle = function_exists('mb_substr') ? mb_substr($safeTitle, 0, 140, 'UTF-8') : substr($safeTitle, 0, 140);
        $folderId = $this->ensureEasyJobMediaFolder($state, $date[0] . '-' . $date[1] . '-' . $date[2] . ' – ' . $shortTitle, $monthId);
        $imported = 0; $updated = 0; $activeIds = []; $imageUrl = null; $available = $existing;
        foreach ($attachments as $attachment) {
            $documentId = $this->easyJobAttachmentText($attachment, ['IdShortCut','IdShortcut','IdDocument','ID','Id','id']);
            if ($documentId === '') { $warnings[] = 'easyjob-Anhang ohne Dokument-ID wurde übersprungen.'; continue; }
            $name = $this->easyJobAttachmentText($attachment, ['FileName','Filename','OriginalFileName','Name','Caption','Description']) ?: 'Veranstaltungsbild-' . $documentId;
            try { $download = $service->downloadShortcut($config, $documentId, $this->easyJobAttachmentText($attachment, ['AccessKey','AccessKeyString','DownloadKey','access_key'])); }
            catch (Throwable $error) { $warnings[] = $name . ': ' . $error->getMessage(); continue; }
            $mime = $download['contentType'];
            $head = bin2hex(substr($download['body'], 0, 12));
            if (str_starts_with($head, 'ffd8ff')) $mime = 'image/jpeg';
            elseif (str_starts_with($head, '89504e470d0a1a0a')) $mime = 'image/png';
            elseif (str_starts_with(substr($download['body'], 0, 6), 'GIF8')) $mime = 'image/gif';
            elseif (substr($download['body'], 0, 4) === 'RIFF' && substr($download['body'], 8, 4) === 'WEBP') $mime = 'image/webp';
            if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','image/avif'], true)) { $warnings[] = $name . ' ist kein unterstütztes Bildformat.'; continue; }
            $hash = hash('sha256', $download['body']);
            $current = $this->first($available, fn(array $asset): bool => ($asset['metadata']['easyjobDocumentId'] ?? '') === $documentId);
            if ($current) $available = array_values(array_filter($available, fn(array $asset): bool => ($asset['id'] ?? '') !== ($current['id'] ?? '')));
            else $current = array_shift($available);
            $src = 'data:' . $mime . ';base64,' . base64_encode($download['body']);
            $metadata = ['source' => 'easyjob','easyjobDocumentType' => 'Veranstaltungsbild','easyjobDocumentId' => $documentId,'easyjobObjectType' => $source,'easyjobObjectId' => $source === 'job' ? $jobId : $projectId,'easyjobProjectId' => $projectId,'easyjobJobId' => $jobId,'easyjobEventId' => $event['id'],'contentHash' => $hash,'contentType' => $mime,'size' => strlen($download['body'])];
            $asset = $this->saveRecord($state, 'mediaAssets', ['name' => $download['fileName'] ?: $name,'folderId' => $folderId,'type' => 'image','src' => $src,'metadata' => $metadata,'deletedAt' => null], $current['id'] ?? null);
            if ($current) $updated++; else $imported++;
            $activeIds[] = $asset['id'];
            $imageUrl ??= $src;
        }
        $removed = 0;
        foreach ($existing as $asset) if (!in_array($asset['id'], $activeIds, true)) { $index = $this->index($state['mediaAssets'], (string)$asset['id']); if ($index !== null) { $state['mediaAssets'][$index]['deletedAt'] = $this->now(); $removed++; } }
        if ($imageUrl && !in_array('imageUrl', $event['lockedFields'] ?? [], true)) { $event['imageUrl'] = $imageUrl; $this->saveRecord($state, 'events', $event, (string)$event['id']); }
        return ['source' => $source, 'found' => count($attachments), 'imported' => $imported, 'updated' => $updated, 'removed' => $removed, 'imageUrl' => $imageUrl, 'warnings' => $warnings];
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $event */
    private function ensureEventChannel(array &$state, array $event): void
    {
        if ($this->first($state['channels'], fn(array $channel): bool => ($channel['eventId'] ?? '') === ($event['id'] ?? ''))) return;
        $this->saveRecord($state, 'channels', ['name' => (string)($event['title'] ?? 'Veranstaltung'), 'description' => 'Automatisch für die Veranstaltung angelegt', 'eventId' => $event['id'], 'items' => [], 'repeatEnabled' => true, 'status' => 'draft']);
    }

    /** @param array<string,mixed> $state @param array<int,string> $ids @return array<int,array<string,mixed>> */
    private function importCrewBrainIds(array &$state, array $ids): array
    {
        $service = $this->crewBrain();
        $config = $state['crewbrain']['config'];
        $mapping = $state['crewbrain']['mapping'];
        $results = [];
        foreach ($ids as $id) {
            try {
                $source = $service->event($config, $id);
                $mapped = $service->mapEvent($source, $mapping);
                $exclusion = $service->titleExclusion($source, (string)$mapped['title'], $this->titleExclusions($state));
                if ($exclusion) {
                    $results[] = ['crewbrainId' => $id, 'action' => 'excluded', 'reason' => $exclusion];
                    continue;
                }
                $result = $this->upsertCrewBrainEvent($state, $mapped);
                $results[] = ['crewbrainId' => $id, 'eventId' => $result['event']['id'], 'action' => $result['action']];
            } catch (Throwable $error) {
                $results[] = ['crewbrainId' => $id, 'action' => 'error', 'reason' => $error->getMessage() ?: 'Import fehlgeschlagen.'];
            }
        }
        return $results;
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function runAutomaticCrewBrainImport(array &$state): array
    {
        if (!$this->crewBrain) return ['ran' => false, 'reason' => 'service_unavailable'];
        if (($state['activeEventSource'] ?? null) !== 'crewbrain') return ['ran' => false, 'reason' => 'source_inactive'];
        $config = $state['crewbrain']['config'];
        if (empty($config['syncEnabled']) || empty($config['encryptedCredential'])) return ['ran' => false, 'reason' => 'disabled'];
        $now = new \DateTimeImmutable('now');
        [$hour, $minute] = array_map('intval', explode(':', (string)($config['autoImportTime'] ?? '03:00')));
        $scheduled = $now->setTime($hour, $minute);
        if ($now < $scheduled) return ['ran' => false, 'reason' => 'not_due'];
        if (!empty($config['lastSyncAt']) && substr((string)$config['lastSyncAt'], 0, 10) === $now->format('Y-m-d')) {
            return ['ran' => false, 'reason' => 'already_ran'];
        }
        $summary = ['ran' => true, 'examined' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'excluded' => 0, 'errors' => 0];
        $details = [];
        try {
            $service = $this->crewBrain();
            $offset = 0;
            $limit = min(max((int)($config['pageSize'] ?? 100), 1), 100);
            $from = $now->modify('-' . (int)($config['lookbackDays'] ?? 30) . ' days')->setTime(0, 0)->format('c');
            $until = $now->modify('+' . (int)($config['lookaheadDays'] ?? 365) . ' days')->setTime(23, 59, 59)->format('c');
            while ($offset < 10_000) {
                $page = $service->listEvents($config, ['from' => $from, 'until' => $until, 'limit' => $limit, 'offset' => $offset]);
                foreach ($page['data'] as $source) {
                    $summary['examined']++;
                    try {
                        $mapped = $service->mapEvent($source, $state['crewbrain']['mapping']);
                        if ($service->titleExclusion($source, (string)$mapped['title'], $this->titleExclusions($state))) {
                            $summary['excluded']++;
                            continue;
                        }
                        $result = $this->upsertCrewBrainEvent($state, $mapped);
                        $summary[$result['action']]++;
                    } catch (Throwable $error) {
                        $summary['errors']++;
                        if (count($details) < 100) $details[] = ['crewbrainId' => $source['ID'] ?? null, 'error' => $error->getMessage()];
                    }
                }
                $offset += max((int)($page['limit'] ?? $limit), 1);
                if ($offset >= (int)($page['totalItems'] ?? 0)) break;
            }
            $config['lastSyncAt'] = $this->now();
            $config['nextSyncAt'] = $scheduled->modify('+1 day')->format('c');
            $config['lastError'] = $summary['errors'] ? 'Einzelne CrewBrain-Veranstaltungen konnten nicht importiert werden.' : null;
        } catch (Throwable $error) {
            $summary['errors']++;
            $details[] = ['error' => $error->getMessage()];
            $config['lastSyncAt'] = $this->now();
            $config['nextSyncAt'] = $scheduled->modify('+1 day')->format('c');
            $config['lastError'] = $error->getMessage();
        }
        $state['crewbrain']['config'] = $config;
        $state['crewbrain']['syncLogs'][] = ['id' => $this->uuid(), 'createdAt' => $this->now(), 'summary' => $summary, 'details' => $details];
        $state['crewbrain']['syncLogs'] = array_slice($state['crewbrain']['syncLogs'], -100);
        $this->audit($state, 'crewbrain.events.auto_imported', (string)($config['id'] ?? 'crewbrain'));
        return $summary + ['details' => $details];
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function runAutomaticEasyJobImport(array &$state): array
    {
        if (!$this->easyJob) return ['ran' => false, 'reason' => 'service_unavailable'];
        if (($state['activeEventSource'] ?? null) !== 'easyjob') return ['ran' => false, 'reason' => 'source_inactive'];
        $config = $state['easyjob']['config'];
        if (empty($config['syncEnabled']) || empty($config['encryptedCredentials'])) return ['ran' => false, 'reason' => 'disabled'];
        $now = new \DateTimeImmutable('now');
        [$hour, $minute] = array_map('intval', explode(':', (string)($config['autoImportTime'] ?? '03:00')));
        $scheduled = $now->setTime($hour, $minute);
        if ($now < $scheduled) return ['ran' => false, 'reason' => 'not_due'];
        if (!empty($config['lastSyncAt']) && substr((string)$config['lastSyncAt'], 0, 10) === $now->format('Y-m-d')) return ['ran' => false, 'reason' => 'already_ran'];
        $summary = ['ran' => true, 'examined' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'excluded' => 0, 'errors' => 0, 'imagesImported' => 0, 'imagesUpdated' => 0, 'imageWarnings' => 0];
        $details = [];
        try {
            $service = $this->easyJob();
            $projects = $service->projects($config, [
                'style' => 'Compact',
                'startdate' => $now->modify('-' . (int)($config['lookbackDays'] ?? 30) . ' days')->setTime(0, 0)->format('c'),
                'enddate' => $now->modify('+' . (int)($config['lookaheadDays'] ?? 365) . ' days')->setTime(23, 59, 59)->format('c'),
            ]);
            foreach (array_slice($projects, 0, 10000) as $listedProject) {
                $projectId = (string)($listedProject['IdProject'] ?? $listedProject['ID'] ?? $listedProject['Id'] ?? '');
                if ($projectId === '') { $summary['errors']++; continue; }
                $summary['examined']++;
                try {
                    $project = $service->project($config, $projectId);
                    if (($config['importMode'] ?? '') !== 'jobs') {
                        $mapped = $service->mapEvent($project, null, $state['easyjob']['mapping']);
                        if ($this->matchingTitleExclusion((string)$mapped['title'], $this->titleExclusions($state))) $summary['excluded']++;
                        else { $result = $this->upsertExternalEvent($state, $mapped); $summary[$result['action']]++; $images = $this->syncEasyJobAdvertisingImages($state, $result['event'], $projectId); $summary['imagesImported'] += $images['imported']; $summary['imagesUpdated'] += $images['updated']; $summary['imageWarnings'] += count($images['warnings']); }
                    }
                    if (($config['importMode'] ?? '') !== 'projects') foreach (($project['Jobs'] ?? []) as $listedJob) {
                        if (!is_array($listedJob)) continue;
                        $jobId = (string)($listedJob['IdJob'] ?? $listedJob['ID'] ?? $listedJob['Id'] ?? '');
                        if ($jobId === '') continue;
                        $mapped = $service->mapEvent($project, $service->job($config, $jobId), $state['easyjob']['mapping']);
                        if ($this->matchingTitleExclusion((string)$mapped['title'], $this->titleExclusions($state))) $summary['excluded']++;
                        else { $result = $this->upsertExternalEvent($state, $mapped); $summary[$result['action']]++; $images = $this->syncEasyJobAdvertisingImages($state, $result['event'], $projectId, $jobId); $summary['imagesImported'] += $images['imported']; $summary['imagesUpdated'] += $images['updated']; $summary['imageWarnings'] += count($images['warnings']); }
                    }
                } catch (Throwable $error) {
                    $summary['errors']++;
                    if (count($details) < 100) $details[] = ['projectId' => $projectId, 'error' => $error->getMessage()];
                }
            }
            $config['lastSyncAt'] = $this->now();
            $config['lastError'] = $summary['errors'] ? 'Einzelne easyjob-Datensätze konnten nicht importiert werden.' : null;
        } catch (Throwable $error) {
            $summary['errors']++;
            $details[] = ['error' => $error->getMessage()];
            $config['lastSyncAt'] = $this->now();
            $config['lastError'] = $error->getMessage();
        }
        $state['easyjob']['config'] = $config;
        $state['easyjob']['syncLogs'][] = ['id' => $this->uuid(), 'createdAt' => $this->now(), 'summary' => $summary, 'details' => $details];
        $state['easyjob']['syncLogs'] = array_slice($state['easyjob']['syncLogs'], -100);
        $this->audit($state, 'easyjob.events.auto_imported', (string)($config['id'] ?? 'easyjob'));
        return $summary + ['details' => $details];
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>,2:bool} */
    private function externalApi(array &$state, string $method, string $path, array $body): array
    {
        if ($path === '/api/v1' && $method === 'GET') {
            return $this->ok(['name' => 'Kiosky External Control API', 'version' => '1.0.0', 'documentation' => 'docs/external-api.md', 'openapi' => 'docs/openapi.yaml']);
        }
        $collections = [
            '/api/v1/displays' => 'displays',
            '/api/v1/display-groups' => 'groups',
            '/api/v1/matrix-displays' => 'matrices',
            '/api/v1/locations' => 'locations',
            '/api/v1/slides' => 'slides',
            '/api/v1/channels' => 'channels',
            '/api/v1/presets' => 'presets',
        ];
        if ($method === 'GET' && isset($collections[$path])) return $this->ok(['data' => $this->listCollection($state, $collections[$path])]);

        if (preg_match('#^/api/v1/channels/([^/]+)/slides$#', $path, $match) && $method === 'PUT') {
            $id = rawurldecode($match[1]);
            $channel = $this->required($state['channels'], $id, 'Kanal');
            $items = is_array($body['items'] ?? null) ? $body['items'] : [];
            if (count($items) > 200) throw new RuntimeException('Ein Kanal darf höchstens 200 Slides enthalten.');
            $mapped = [];
            foreach ($items as $item) {
                if (!is_array($item)) throw new RuntimeException('Playlist-Eintrag ist ungültig.');
                $slideId = trim((string)($item['slideId'] ?? ''));
                $this->required($state['slides'], $slideId, 'Slide');
                $duration = (int)($item['durationSeconds'] ?? 12);
                $transition = (string)($item['transition'] ?? 'inherit');
                if ($duration < 3 || $duration > 600) throw new RuntimeException('durationSeconds muss zwischen 3 und 600 liegen.');
                if (!in_array($transition, ['inherit', 'fade', 'slide', 'zoom', 'none'], true)) throw new RuntimeException('transition ist ungültig.');
                $mapped[] = ['slideId' => $slideId, 'durationSeconds' => $duration, 'transition' => $transition];
            }
            return $this->ok(['channel' => $this->saveRecord($state, 'channels', array_replace($channel, ['items' => $mapped]), $id)], true);
        }
        if ($path === '/api/v1/assignments' && $method === 'GET') return $this->ok(['data' => $state['assignments']]);
        if ($path === '/api/v1/assignments' && in_array($method, ['POST', 'PUT'], true)) {
            $assignment = $this->saveAssignment($state, $body);
            return $method === 'POST' ? $this->created(['assignment' => $assignment]) : $this->ok(['assignment' => $assignment], true);
        }
        if (preg_match('#^/api/v1/assignments/([^/]+)$#', $path, $match) && $method === 'DELETE') {
            $this->purge($state['assignments'], rawurldecode($match[1]));
            return $this->ok(['ok' => true], true);
        }
        if (preg_match('#^/api/v1/(displays|display-groups)/([^/]+)/(channel|slide)$#', $path, $match) && $method === 'PUT') {
            $assignment = $this->saveAssignment($state, array_replace($body, [
                'target' => ['type' => $match[1] === 'displays' ? 'display' : 'group', 'id' => rawurldecode($match[2])],
                'content' => ['type' => $match[3], 'id' => (string)($body['id'] ?? $body['contentId'] ?? '')],
            ]));
            return $this->ok(['assignment' => $assignment], true);
        }
        if ($path === '/api/v1/emergencies/active' && $method === 'GET') {
            return $this->ok(['alert' => $this->activeEmergency($state)]);
        }
        if ($path === '/api/v1/emergencies' && $method === 'POST') {
            $warning = $this->externalWarning($state, $body, 'external_api');
            return $this->created(['alert' => $warning]);
        }
        if ($path === '/api/v1/emergencies/active' && $method === 'DELETE') {
            $this->endExternalWarnings($state);
            return $this->ok(['ok' => true], true);
        }
        if (preg_match('#^/api/v1/presets/([^/]+)/execute$#', $path, $match) && $method === 'POST') {
            $preset = $this->required($state['presets'], rawurldecode($match[1]), 'Preset');
            $config = is_array($preset['config'] ?? null) ? $preset['config'] : [];
            $targets = $this->externalTargets($body['targets'] ?? $config['targets'] ?? []);
            if (!$targets) throw new RuntimeException('Mindestens ein Ziel ist erforderlich.');
            $type = (string)($preset['type'] ?? 'channel');
            if ($type === 'channel') {
                $channelId = (string)($body['channelId'] ?? $preset['channelId'] ?? $config['channelId'] ?? '');
                $result = ['action' => 'channel_assigned', 'assignments' => array_map(
                    fn(array $target): array => $this->saveAssignment($state, ['target' => $target, 'content' => ['type' => 'channel', 'id' => $channelId], 'priority' => $body['priority'] ?? 100]),
                    $targets
                )];
            } elseif ($type === 'emergency') {
                $result = ['action' => 'emergency_activated', 'alert' => $this->externalWarning($state, array_replace($config, $body, ['targets' => $targets]), 'external_preset')];
            } else {
                $removed = $this->clearAssignmentTargets($state, $targets);
                if (($body['endEmergency'] ?? true) !== false) $this->endExternalWarnings($state);
                $result = ['action' => 'reset', 'removedAssignments' => $removed, 'emergencyEnded' => ($body['endEmergency'] ?? true) !== false];
            }
            $execution = $this->record(['presetId' => $preset['id'], 'targets' => $targets, 'result' => $result, 'executedAt' => $this->now()]);
            $state['presetExecutions'][] = $execution;
            $state['presetExecutions'] = array_slice($state['presetExecutions'], -100);
            return $this->created(['execution' => $execution]);
        }
        if ($path === '/api/v1/preset-executions' && $method === 'GET') return $this->ok(['data' => array_reverse($state['presetExecutions'])]);
        if ($path === '/api/v1/presets/status' && $method === 'GET') return $this->ok(['activeAssignments' => $this->activeAssignments($state), 'executions' => array_reverse($state['presetExecutions'])]);
        if ($path === '/api/v1/presets/stop' && $method === 'POST') {
            $targets = $this->externalTargets($body['targets'] ?? []);
            $removed = $this->clearAssignmentTargets($state, $targets);
            if (($body['endWarnings'] ?? true) !== false) $this->endExternalWarnings($state);
            return $this->ok(['removedAssignments' => $removed], true);
        }
        if ($path === '/api/v1/warnings/webhook' && $method === 'POST') {
            $warning = $this->externalWarning($state, $body, (string)($body['source'] ?? 'webhook'), ($body['publish'] ?? true) !== false);
            return $this->created(['warning' => $warning]);
        }
        throw new RuntimeException('API-v1-Endpunkt wurde nicht gefunden.', 404);
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $input @return array<string,mixed> */
    private function saveAssignment(array &$state, array $input): array
    {
        $target = $this->externalTarget($input['target'] ?? null);
        $content = is_array($input['content'] ?? null) ? $input['content'] : [];
        $contentType = (string)($content['type'] ?? '');
        $contentId = trim((string)($content['id'] ?? ''));
        if (!in_array($contentType, ['channel', 'slide'], true)) throw new RuntimeException('Inhaltstyp muss channel oder slide sein.');
        $this->required($state[$contentType === 'channel' ? 'channels' : 'slides'], $contentId, $contentType === 'channel' ? 'Kanal' : 'Slide');
        $this->assertExternalTarget($state, $target);
        $priority = (int)($input['priority'] ?? 100);
        if ($priority < 0 || $priority > 1000) throw new RuntimeException('priority muss zwischen 0 und 1000 liegen.');
        $startsAt = $this->externalDate($input['startsAt'] ?? null, 'startsAt');
        $expiresAt = $this->externalDate($input['expiresAt'] ?? null, 'expiresAt');
        if ($startsAt && $expiresAt && strtotime($expiresAt) <= strtotime($startsAt)) throw new RuntimeException('expiresAt muss nach startsAt liegen.');
        $current = $this->first($state['assignments'], static fn(array $item): bool => ($item['target'] ?? null) === $target);
        $assignment = $this->record([
            'target' => $target, 'content' => ['type' => $contentType, 'id' => $contentId], 'priority' => $priority,
            'startsAt' => $startsAt, 'expiresAt' => $expiresAt, 'source' => 'external_api', 'createdBy' => $this->identity['id'],
        ], $current['id'] ?? null);
        if ($current) $assignment['createdAt'] = $current['createdAt'];
        $index = $current ? $this->index($state['assignments'], (string)$current['id']) : null;
        if ($index === null) $state['assignments'][] = $assignment; else $state['assignments'][$index] = $assignment;
        $this->audit($state, 'external.assignment.upserted', (string)$assignment['id']);
        return $assignment;
    }

    /** @return array{type:string,id:string} */
    private function externalTarget(mixed $value): array
    {
        if (!is_array($value)) throw new RuntimeException('Ziel fehlt oder ist ungültig.');
        $type = (string)($value['type'] ?? '');
        $id = trim((string)($value['id'] ?? ''));
        if (!in_array($type, ['display', 'group', 'matrix', 'location', 'global'], true) || $id === '') throw new RuntimeException('Ziel ist ungültig.');
        return ['type' => $type, 'id' => $id];
    }

    /** @param array<string,mixed> $state @param array{type:string,id:string} $target */
    private function assertExternalTarget(array $state, array $target): void
    {
        if ($target['type'] === 'global' && $target['id'] === 'global') return;
        $collection = ['display' => 'displays', 'group' => 'groups', 'matrix' => 'matrices', 'location' => 'locations'][$target['type']] ?? '';
        if (!$collection || !$this->first($this->active($state[$collection]), fn(array $item): bool => $item['id'] === $target['id'])) throw new RuntimeException('Das Ausspielungsziel wurde nicht gefunden.');
    }

    /** @return array<int,array{type:string,id:string}> */
    private function externalTargets(mixed $value): array
    {
        if (!is_array($value)) return [];
        $targets = [];
        foreach ($value as $item) {
            $target = $this->externalTarget($item);
            $targets[$target['type'] . ':' . $target['id']] = $target;
        }
        if (count($targets) > 500) throw new RuntimeException('Höchstens 500 Ziele sind erlaubt.');
        return array_values($targets);
    }

    private function externalDate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') return null;
        $time = strtotime((string)$value);
        if ($time === false) throw new RuntimeException($field . ' muss ein gültiger ISO-8601-Zeitpunkt sein.');
        return gmdate('c', $time);
    }

    /** @param array<string,mixed> $state @return array<int,array<string,mixed>> */
    private function activeAssignments(array $state): array
    {
        $now = time();
        return array_values(array_filter($state['assignments'], static function (array $item) use ($now): bool {
            $start = !empty($item['startsAt']) ? strtotime((string)$item['startsAt']) : false;
            $end = !empty($item['expiresAt']) ? strtotime((string)$item['expiresAt']) : false;
            return ($start === false || $start <= $now) && ($end === false || $end > $now);
        }));
    }

    /** @param array<string,mixed> $state @param array<int,array{type:string,id:string}> $targets */
    private function clearAssignmentTargets(array &$state, array $targets): int
    {
        $keys = array_map(static fn(array $target): string => $target['type'] . ':' . $target['id'], $targets);
        $before = count($state['assignments']);
        $state['assignments'] = array_values(array_filter($state['assignments'], static fn(array $item): bool => !in_array(($item['target']['type'] ?? '') . ':' . ($item['target']['id'] ?? ''), $keys, true)));
        return $before - count($state['assignments']);
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $input @return array<string,mixed> */
    private function externalWarning(array &$state, array $input, string $source, bool $publish = true): array
    {
        $targets = $this->externalTargets($input['targets'] ?? []);
        if (!$targets) throw new RuntimeException('Mindestens ein Ziel ist erforderlich.');
        foreach ($targets as $target) $this->assertExternalTarget($state, $target);
        $title = trim((string)($input['title'] ?? ''));
        $instructions = trim((string)($input['instructions'] ?? $input['message'] ?? ''));
        if ($title === '' || $instructions === '') throw new RuntimeException('Titel und Anweisungen sind erforderlich.');
        return $this->saveRecord($state, 'warnings', [
            'source' => $source,
            'warningType' => (string)($input['warningType'] ?? $input['type'] ?? 'custom'),
            'severity' => (int)($input['severity'] ?? 5),
            'title' => $title, 'description' => (string)($input['description'] ?? ''),
            'instructions' => $instructions, 'priority' => (int)($input['priority'] ?? 900),
            'targets' => array_map(static fn(array $target): string => $target['type'] === 'global' ? 'global' : $target['type'] . ':' . $target['id'], $targets),
            'document' => is_array($input['document'] ?? null) ? $input['document'] : [],
            'returnBehavior' => 'resume_current_schedule',
            'status' => $publish ? 'active' : 'pending',
            ...($publish ? ['publishedAt' => $this->now()] : []),
        ]);
    }

    /** @param array<string,mixed> $state @return array<string,mixed>|null */
    private function activeEmergency(array $state): ?array
    {
        return $this->first(array_reverse($state['warnings']), static fn(array $item): bool => ($item['status'] ?? '') === 'active' && in_array($item['source'] ?? '', ['external_api', 'external_preset'], true));
    }

    /** @param array<string,mixed> $state */
    private function endExternalWarnings(array &$state): void
    {
        foreach ($state['warnings'] as &$warning) {
            if (($warning['status'] ?? '') === 'active' && in_array($warning['source'] ?? '', ['external_api', 'external_preset'], true)) {
                $warning['status'] = 'ended';
                $warning['endedAt'] = $this->now();
            }
        }
        unset($warning);
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>,2:bool} */
    private function player(array &$state, string $method, string $slug, string $action, array $body, string $playerKey): array
    {
        $display = $this->first($this->listCollection($state, 'displays'), fn(array $item): bool => ($item['slug'] ?? '') === $slug);
        if (!$display || !hash_equals((string)($display['playerKey'] ?? ''), $playerKey)) throw new RuntimeException('Display wurde nicht gefunden.', 404);
        if ($action === 'heartbeat' && $method === 'POST') {
            $body['lastSeenAt'] = $this->now();
            $body['online'] = true;
            $this->saveRecord($state, 'displays', array_replace($display, $body), $display['id']);
            $state['commands'] = array_values(array_filter($state['commands'], fn(array $item): bool => ($item['displayId'] ?? '') !== $display['id']));
            return $this->ok(['ok' => true], true);
        }
        if ($action === 'proof' && $method === 'POST') {
            return $this->ok(['disabled' => true]);
        }
        if ($action !== 'state' || $method !== 'GET') throw new RuntimeException('Methode nicht erlaubt.', 405);

        $matrixLayout = $this->matrixLayout($state, (string)$display['id']);
        $assignment = $this->resolveAssignment($state, $display, $matrixLayout);
        $selection = $this->resolvedChannelSelection($state, $display, $matrixLayout);
        $channel = $selection['channel'];
        $directSlide = $selection['directSlide'] ?? null;
        if ($assignment) {
            $content = is_array($assignment['content'] ?? null) ? $assignment['content'] : [];
            if (($content['type'] ?? '') === 'channel') {
                $channel = $this->first($this->active($state['channels']), fn(array $item): bool => $item['id'] === ($content['id'] ?? ''));
            } elseif (($content['type'] ?? '') === 'slide') {
                $directSlide = $this->first($this->active($state['slides']), fn(array $item): bool => $item['id'] === ($content['id'] ?? ''));
                $channel = null;
            }
        }
        $slides = [];
        foreach ($directSlide ? [['slideId' => $directSlide['id'], 'durationSeconds' => 12, 'transition' => 'none']] : ($channel['items'] ?? []) as $item) {
            $slide = $this->first($this->active($state['slides']), fn(array $candidate): bool => $candidate['id'] === ($item['slideId'] ?? ''));
            if (!$slide) continue;
            $event = !empty($slide['eventId']) ? $this->first($state['events'], fn(array $candidate): bool => $candidate['id'] === $slide['eventId']) : null;
            $slides[] = $item + ['slide' => $slide, 'event' => $event];
        }
        $targets = ['global', 'display:' . $display['id']];
        foreach ($display['groupIds'] ?? [] as $groupId) $targets[] = 'group:' . $groupId;
        if (!empty($display['locationId'])) $targets[] = 'location:' . $display['locationId'];
        if ($matrixLayout) $targets[] = 'matrix:' . $matrixLayout['id'];
        $warning = $this->first(array_reverse($state['warnings']), fn(array $item): bool => ($item['status'] ?? '') === 'active' && count(array_intersect($targets, $item['targets'] ?? [])) > 0);
        $emergency = $warning ? [
            'id' => $warning['id'], 'type' => $warning['warningType'] ?? 'info', 'title' => $warning['title'] ?? '',
            'message' => $warning['instructions'] ?? ($warning['description'] ?? ''), 'description' => $warning['description'] ?? '',
            'targets' => $warning['targets'] ?? [], 'active' => true, 'source' => $warning['source'] ?? 'manual',
            'severity' => $warning['severity'] ?? 1, 'endsAt' => $warning['endsAt'] ?? null, 'document' => $warning['document'] ?? [],
        ] : null;
        $commands = array_values(array_filter($state['commands'], fn(array $item): bool => ($item['displayId'] ?? '') === $display['id']));
        $serverTime = $this->now();
        $matrixMode = $matrixLayout && !empty($selection['matrixMode']);
        $playbackSync = [
            'scope' => $matrixMode ? 'matrix' : 'content',
            'key' => ($matrixMode ? $matrixLayout['id'] . ':' : $display['id'] . ':') . $selection['sourceKey'],
            'epochMs' => $this->milliseconds((string)$selection['epoch'], $serverTime),
            'serverTimeMs' => $this->milliseconds($serverTime, $serverTime),
        ];
        return $this->ok([
            'display' => $display, 'channel' => $channel, 'directSlide' => $directSlide, 'assignment' => $assignment,
            'slides' => $slides, 'emergency' => $emergency, 'warning' => $emergency,
            'matrix' => $matrixLayout ? array_replace($matrixLayout, ['mode' => $matrixMode ? 'matrix' : 'individual']) : null,
            'playbackSync' => $playbackSync, 'commands' => $commands, 'serverTime' => $serverTime,
        ]);
    }

    /** @param array<string,mixed> $state @param array<string,mixed>|null $matrix @return array<string,mixed>|null */
    private function resolveAssignment(array $state, array $display, ?array $matrix): ?array
    {
        $now = time();
        $candidates = array_values(array_filter($state['assignments'], function (array $item) use ($display, $matrix, $now): bool {
            $startsAt = !empty($item['startsAt']) ? strtotime((string)$item['startsAt']) : false;
            $expiresAt = !empty($item['expiresAt']) ? strtotime((string)$item['expiresAt']) : false;
            if (($startsAt !== false && $startsAt > $now) || ($expiresAt !== false && $expiresAt <= $now)) return false;
            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            return (($target['type'] ?? '') === 'display' && ($target['id'] ?? '') === $display['id'])
                || (($target['type'] ?? '') === 'group' && in_array($target['id'] ?? '', $display['groupIds'] ?? [], true))
                || (($target['type'] ?? '') === 'matrix' && $matrix && ($target['id'] ?? '') === $matrix['id'])
                || (($target['type'] ?? '') === 'location' && ($target['id'] ?? '') === ($display['locationId'] ?? ''))
                || (($target['type'] ?? '') === 'global' && ($target['id'] ?? '') === 'global');
        }));
        $specificity = ['global' => 1, 'location' => 2, 'group' => 3, 'matrix' => 4, 'display' => 5];
        usort($candidates, static function (array $left, array $right) use ($specificity): int {
            $leftType = (string)($left['target']['type'] ?? 'global');
            $rightType = (string)($right['target']['type'] ?? 'global');
            return ($specificity[$rightType] ?? 0) <=> ($specificity[$leftType] ?? 0)
                ?: (int)($right['priority'] ?? 100) <=> (int)($left['priority'] ?? 100)
                ?: strcmp((string)($right['updatedAt'] ?? ''), (string)($left['updatedAt'] ?? ''));
        });
        return $candidates[0] ?? null;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $display
     * @param array<string,mixed>|null $matrix
     * @return array{channel:array<string,mixed>|null,directSlide:array<string,mixed>|null,sourceKey:string,epoch:string,matrixMode:bool}
     */
    private function resolvedChannelSelection(array $state, array $display, ?array $matrix): array
    {
        $now = $this->now();
        $schedules = array_values(array_filter($state['schedules'], function (array $item) use ($display, $now): bool {
            if (($item['status'] ?? '') !== 'published' || (empty($item['channelId']) && empty($item['slideId']) && empty($item['contentId'])) || !$this->scheduleActive($item, $now)) return false;
            return (($item['targetType'] ?? '') === 'display' && ($item['targetId'] ?? '') === $display['id'])
                || (($item['targetType'] ?? '') === 'group' && in_array($item['targetId'] ?? '', $display['groupIds'] ?? [], true));
        }));
        if ($matrix) {
            foreach ($state['schedules'] as $schedule) {
                if (($schedule['status'] ?? '') === 'published' && ($schedule['targetType'] ?? '') === 'matrix' && ($schedule['targetId'] ?? '') === $matrix['id'] && $this->scheduleActive($schedule, $now)) {
                    $schedules[] = $schedule;
                }
            }
        }
        usort($schedules, static function (array $a, array $b): int {
            $stack = (int)($b['stackOrder'] ?? 0) <=> (int)($a['stackOrder'] ?? 0);
            if ($stack !== 0) return $stack;
            $created = strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
            if ($created !== 0) return $created;
            $ranks = ['normal' => 1, 'high' => 2, 'takeover' => 3];
            return ($ranks[$b['priority'] ?? 'normal'] ?? 0) <=> ($ranks[$a['priority'] ?? 'normal'] ?? 0);
        });
        $schedule = $schedules[0] ?? null;
        $matrixMode = $matrix && (($schedule['targetType'] ?? '') === 'matrix' || (!$schedule && !empty($matrix['defaultChannelId'])));
        $channelId = $schedule['channelId'] ?? ($matrixMode ? ($matrix['defaultChannelId'] ?? '') : ($display['defaultChannelId'] ?? ''));
        $channel = $channelId ? $this->first($this->active($state['channels']), fn(array $item): bool => $item['id'] === $channelId) : null;
        $slideId = ($schedule['contentType'] ?? '') === 'slide' ? ($schedule['slideId'] ?? $schedule['contentId'] ?? '') : '';
        $directSlide = $slideId ? $this->first($this->active($state['slides']), fn(array $item): bool => $item['id'] === $slideId) : null;
        if ($directSlide) $channel = null;
        return [
            'channel' => $channel,
            'directSlide' => $directSlide,
            'sourceKey' => $schedule ? 'schedule:' . $schedule['id'] : 'default:' . $channelId . ':' . ($channel['updatedAt'] ?? ''),
            'epoch' => (string)($schedule['startsAt'] ?? $directSlide['updatedAt'] ?? $channel['updatedAt'] ?? $now),
            'matrixMode' => (bool)$matrixMode,
        ];
    }

    /** @param array<string,mixed> $state @return array<string,mixed>|null */
    private function matrixLayout(array $state, string $displayId): ?array
    {
        $matrix = $this->first($this->active($state['matrices']), static fn(array $candidate): bool =>
            ($candidate['status'] ?? '') === 'active'
            && in_array($displayId, array_column(is_array($candidate['members'] ?? null) ? $candidate['members'] : [], 'displayId'), true)
        );
        if (!$matrix) return null;
        $members = is_array($matrix['members'] ?? null) ? $matrix['members'] : [];
        $member = $this->first($members, static fn(array $candidate): bool => ($candidate['displayId'] ?? '') === $displayId);
        if (!$member) return null;
        $columns = max(1, (int)($matrix['columns'] ?? 1));
        $rows = max(1, (int)($matrix['rows'] ?? 1));
        $widthFor = static fn(array $item): int => (int)(($item['orientation'] ?? '') === 'portrait' ? ($item['physicalHeight'] ?? 1920) : ($item['physicalWidth'] ?? 1920));
        $heightFor = static fn(array $item): int => (int)(($item['orientation'] ?? '') === 'portrait' ? ($item['physicalWidth'] ?? 1080) : ($item['physicalHeight'] ?? 1080));
        $columnWidths = [];
        for ($column = 0; $column < $columns; $column++) {
            $columnWidths[] = max([0, ...array_map($widthFor, array_filter($members, static fn(array $item): bool => (int)($item['column'] ?? 0) === $column))]);
        }
        $rowHeights = [];
        for ($row = 0; $row < $rows; $row++) {
            $rowHeights[] = max([0, ...array_map($heightFor, array_filter($members, static fn(array $item): bool => (int)($item['row'] ?? 0) === $row))]);
        }
        $crop = is_array($member['crop'] ?? null) ? $member['crop'] : [];
        $memberColumn = (int)($member['column'] ?? 0);
        $memberRow = (int)($member['row'] ?? 0);
        return [
            'id' => $matrix['id'],
            'name' => $matrix['name'] ?? 'Matrix',
            'rows' => $rows,
            'columns' => $columns,
            'defaultChannelId' => $matrix['defaultChannelId'] ?? null,
            'totalResolution' => ['width' => array_sum($columnWidths), 'height' => array_sum($rowHeights)],
            'viewport' => [
                'x' => is_numeric($crop['x'] ?? null) ? (float)$crop['x'] : array_sum(array_slice($columnWidths, 0, $memberColumn)) + (float)($member['offsetX'] ?? 0),
                'y' => is_numeric($crop['y'] ?? null) ? (float)$crop['y'] : array_sum(array_slice($rowHeights, 0, $memberRow)) + (float)($member['offsetY'] ?? 0),
                'width' => is_numeric($crop['width'] ?? null) ? (float)$crop['width'] : $widthFor($member),
                'height' => is_numeric($crop['height'] ?? null) ? (float)$crop['height'] : $heightFor($member),
                'scale' => (float)($member['scale'] ?? 1),
                'row' => $memberRow,
                'column' => $memberColumn,
            ],
        ];
    }

    /** @param array<string,mixed> $schedule */
    private function scheduleActive(array $schedule, string $nowValue): bool
    {
        try {
            $now = new \DateTimeImmutable($nowValue);
            $start = new \DateTimeImmutable((string)($schedule['startsAt'] ?? ''));
            $end = new \DateTimeImmutable((string)($schedule['endsAt'] ?? ''));
        } catch (Throwable) {
            return false;
        }
        if ($end <= $start) return false;
        $recurrence = (string)($schedule['recurrence'] ?? 'once');
        if ($recurrence === 'once') return $start <= $now && $end > $now;
        if ($now < $start) return false;
        $duration = $end->getTimestamp() - $start->getTimestamp();
        $period = $recurrence === 'weekly' ? 7 * 86400 : 86400;
        $elapsed = $now->getTimestamp() - $start->getTimestamp();
        return ($elapsed % $period) < $duration;
    }

    private function milliseconds(string $value, string $fallback): int
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) $timestamp = strtotime($fallback);
        return (int)($timestamp !== false ? $timestamp * 1000 : time() * 1000);
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>,2:bool} */
    private function collectionRoute(array &$state, string $method, string $collection, string $singular, array $body): array
    {
        if ($method === 'GET') return $this->ok(['data' => $this->listCollection($state, $collection)]);
        if ($method === 'POST') {
            if ($collection === 'displays' && empty($body['playerKey'])) $body['playerKey'] = bin2hex(random_bytes(24));
            if ($collection === 'displays') $body += ['groupIds' => [], 'online' => false, 'cached' => false, 'channel' => 'Kein Standardkanal'];
            if ($collection === 'warnings') $body += ['status' => 'draft', 'source' => 'manual', 'targets' => []];
            if ($collection === 'schedules') {
                $contentType = ($body['contentType'] ?? 'channel') === 'slide' ? 'slide' : 'channel';
                $contentId = (string)($body['contentId'] ?? $body[$contentType . 'Id'] ?? '');
                $body['contentType'] = $contentType;
                $body['contentId'] = $contentId;
                $body['channelId'] = $contentType === 'channel' ? $contentId : null;
                $body['slideId'] = $contentType === 'slide' ? $contentId : null;
                if (!isset($body['stackOrder'])) {
                    $highest = max([0, ...array_map(static fn(array $schedule): int =>
                        ($schedule['targetType'] ?? '') === ($body['targetType'] ?? '')
                        && ($schedule['targetId'] ?? '') === ($body['targetId'] ?? '')
                            ? (int)($schedule['stackOrder'] ?? 0)
                            : 0, $state['schedules'])]);
                    $body['stackOrder'] = max((int)floor(microtime(true) * 1000), $highest + 1);
                }
            }
            if ($collection === 'matrices') $this->validateMatrix($state, $body);
            $record = $this->saveRecord($state, $collection, $body);
            if ($collection === 'groups') $this->syncGroupMembers($state, $record);
            return $this->created([$singular => $record]);
        }
        throw new RuntimeException('Methode nicht erlaubt.', 405);
    }

    /** @param array<string,mixed> $state @return array<int,array<string,mixed>> */
    private function listCollection(array $state, string $collection): array
    {
        $items = $this->active($state[$collection]);
        if ($collection === 'groups') {
            return array_map(fn(array $item): array => $item + ['displayIds' => [], 'status' => 'active'], $items);
        }
        if ($collection === 'locations') {
            return array_map(function (array $item) use ($state): array {
                $item['displayCount'] = count(array_filter($this->active($state['displays']), fn(array $display): bool => ($display['locationId'] ?? '') === $item['id']));
                return $item;
            }, $items);
        }
        if ($collection === 'presets') {
            return array_map(function (array $item) use ($state): array {
                $channel = !empty($item['channelId']) ? $this->first($state['channels'], fn(array $candidate): bool => $candidate['id'] === $item['channelId']) : null;
                $item['channelName'] = $channel['name'] ?? null;
                return $item;
            }, $items);
        }
        if ($collection === 'schedules') {
            return array_map(function (array $item) use ($state): array {
                $channel = !empty($item['channelId']) ? $this->first($state['channels'], fn(array $candidate): bool => $candidate['id'] === $item['channelId']) : null;
                $slide = !empty($item['slideId']) ? $this->first($state['slides'], fn(array $candidate): bool => $candidate['id'] === $item['slideId']) : null;
                $item['contentType'] = $item['contentType'] ?? ($slide ? 'slide' : 'channel');
                $item['contentId'] = $item['contentId'] ?? ($slide['id'] ?? $channel['id'] ?? '');
                $item['contentName'] = $slide['name'] ?? $channel['name'] ?? 'Unbekannter Inhalt';
                $item['channelName'] = $channel['name'] ?? null;
                $item['slideName'] = $slide['name'] ?? null;
                return $item;
            }, $items);
        }
        if ($collection === 'matrices') {
            return array_map(function (array $item) use ($state): array {
                $members = is_array($item['members'] ?? null) ? $item['members'] : [];
                $columns = max(1, (int)($item['columns'] ?? 1));
                $rows = max(1, (int)($item['rows'] ?? 1));
                $columnWidths = [];
                for ($column = 0; $column < $columns; $column++) {
                    $columnWidths[] = max([0, ...array_map(
                        static fn(array $member): int => (int)(($member['orientation'] ?? '') === 'portrait' ? ($member['physicalHeight'] ?? 1920) : ($member['physicalWidth'] ?? 1920)),
                        array_filter($members, static fn(array $member): bool => (int)($member['column'] ?? 0) === $column)
                    )]);
                }
                $rowHeights = [];
                for ($row = 0; $row < $rows; $row++) {
                    $rowHeights[] = max([0, ...array_map(
                        static fn(array $member): int => (int)(($member['orientation'] ?? '') === 'portrait' ? ($member['physicalWidth'] ?? 1080) : ($member['physicalHeight'] ?? 1080)),
                        array_filter($members, static fn(array $member): bool => (int)($member['row'] ?? 0) === $row)
                    )]);
                }
                $channel = !empty($item['defaultChannelId']) ? $this->first($state['channels'], fn(array $candidate): bool => $candidate['id'] === $item['defaultChannelId']) : null;
                $item['channelName'] = $channel['name'] ?? null;
                $item['totalResolution'] = ['width' => array_sum($columnWidths), 'height' => array_sum($rowHeights)];
                return $item;
            }, $items);
        }
        if ($collection !== 'displays') return $items;
        return array_map(function (array $item) use ($state): array {
            $channel = !empty($item['defaultChannelId']) ? $this->first($state['channels'], fn(array $candidate): bool => $candidate['id'] === $item['defaultChannelId']) : null;
            $location = !empty($item['locationId']) ? $this->first($state['locations'], fn(array $candidate): bool => $candidate['id'] === $item['locationId']) : null;
            $matrix = $this->first($this->active($state['matrices']), fn(array $candidate): bool => in_array($item['id'], array_column($candidate['members'] ?? [], 'displayId'), true));
            $member = $matrix ? $this->first($matrix['members'] ?? [], fn(array $candidate): bool => $candidate['displayId'] === $item['id']) : null;
            $item['channel'] = $channel['name'] ?? 'Kein Standardkanal';
            $currentChannel = !empty($item['channelId']) ? $this->first($state['channels'], fn(array $candidate): bool => $candidate['id'] === $item['channelId']) : null;
            $currentSlide = !empty($item['slideId']) ? $this->first($state['slides'], fn(array $candidate): bool => $candidate['id'] === $item['slideId']) : null;
            $item['currentChannelId'] = $item['channelId'] ?? null;
            $item['currentChannelName'] = $currentChannel['name'] ?? null;
            $item['currentSlideId'] = $item['slideId'] ?? null;
            $item['currentSlideName'] = $currentSlide['name'] ?? null;
            $item['location'] = $location['name'] ?? null;
            $item['resolution'] = ['width' => (int)($item['width'] ?? 1920), 'height' => (int)($item['height'] ?? 1080)];
            $item['matrix'] = $matrix && $member ? ['id' => $matrix['id'], 'name' => $matrix['name'], 'row' => (int)($member['row'] ?? 0), 'column' => (int)($member['column'] ?? 0)] : null;
            return $item;
        }, $items);
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>,2:bool} */
    private function itemRoute(array &$state, string $method, string $collection, string $singular, string $id, array $body, bool $trash = false): array
    {
        if ($method === 'PUT') {
            if ($collection === 'matrices') $this->validateMatrix($state, $body, $id);
            $record = $this->saveRecord($state, $collection, $body, $id);
            if ($collection === 'groups') $this->syncGroupMembers($state, $record);
            return $this->ok([$singular => $record], true);
        }
        if ($method === 'DELETE') {
            if ($collection === 'groups') {
                foreach ($state['displays'] as &$display) $display['groupIds'] = array_values(array_diff($display['groupIds'] ?? [], [$id]));
                unset($display);
            }
            if ($collection === 'locations') {
                foreach ($state['displays'] as &$display) if (($display['locationId'] ?? '') === $id) unset($display['locationId']);
                unset($display);
            }
            if ($collection === 'displays') {
                foreach ($state['groups'] as &$group) $group['displayIds'] = array_values(array_diff($group['displayIds'] ?? [], [$id]));
                unset($group);
                foreach ($state['matrices'] as &$matrix) $matrix['members'] = array_values(array_filter($matrix['members'] ?? [], static fn(array $member): bool => ($member['displayId'] ?? '') !== $id));
                unset($matrix);
            }
            return $trash ? $this->trashRecord($state, $collection, $singular === 'group' ? 'display_group' : $singular, $id) : $this->deleteRecord($state, $collection, $id);
        }
        throw new RuntimeException('Methode nicht erlaubt.', 405);
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $group */
    private function syncGroupMembers(array &$state, array $group): void
    {
        $groupId = (string)$group['id'];
        $displayIds = array_values(array_unique(array_map('strval', is_array($group['displayIds'] ?? null) ? $group['displayIds'] : [])));
        foreach ($state['displays'] as &$display) {
            $groups = array_values(array_diff(array_map('strval', $display['groupIds'] ?? []), [$groupId]));
            if (in_array((string)$display['id'], $displayIds, true)) $groups[] = $groupId;
            $display['groupIds'] = array_values(array_unique($groups));
        }
        unset($display);
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $input */
    private function validateMatrix(array $state, array $input, ?string $matrixId = null): void
    {
        $rows = (int)($input['rows'] ?? 0);
        $columns = (int)($input['columns'] ?? 0);
        $members = is_array($input['members'] ?? null) ? $input['members'] : [];
        if ($rows < 1 || $rows > 20 || $columns < 1 || $columns > 20) throw new RuntimeException('Das Matrix-Raster muss zwischen 1×1 und 20×20 liegen.');
        $positions = [];
        $displayIds = [];
        foreach ($members as $member) {
            if (!is_array($member)) throw new RuntimeException('Matrix-Mitglied ist ungültig.');
            $displayId = (string)($member['displayId'] ?? '');
            $row = (int)($member['row'] ?? -1);
            $column = (int)($member['column'] ?? -1);
            $this->required($state['displays'], $displayId, 'Display');
            if ($row < 0 || $column < 0 || $row >= $rows || $column >= $columns) throw new RuntimeException('Eine Display-Position liegt außerhalb des Matrix-Rasters.');
            $position = $row . ':' . $column;
            if (isset($positions[$position])) throw new RuntimeException('Jedes Matrix-Feld darf nur einmal belegt sein.');
            if (isset($displayIds[$displayId])) throw new RuntimeException('Ein Display darf in einer Matrix nur einmal vorkommen.');
            $positions[$position] = true;
            $displayIds[$displayId] = true;
            if (($input['status'] ?? '') !== 'active') continue;
            $conflict = $this->first($this->active($state['matrices']), static fn(array $matrix): bool =>
                ($matrix['status'] ?? '') === 'active'
                && ($matrix['id'] ?? '') !== $matrixId
                && in_array($displayId, array_column(is_array($matrix['members'] ?? null) ? $matrix['members'] : [], 'displayId'), true)
            );
            if ($conflict) throw new RuntimeException('Das Display ist bereits der aktiven Matrix „' . ($conflict['name'] ?? 'Unbekannt') . '“ zugeordnet.');
        }
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $body @return array<string,mixed> */
    private function saveSlide(array &$state, array $body, ?string $id = null): array
    {
        $current = $id ? $this->required($state['slides'], $id, 'Slide') : null;
        $version = (int)($current['currentVersion'] ?? 0) + 1;
        $orientation = (string)($body['orientation'] ?? 'landscape');
        $body += ['width' => $orientation === 'portrait' ? 1080 : 1920, 'height' => $orientation === 'portrait' ? 1920 : 1080, 'status' => 'draft'];
        $body['currentVersion'] = $version;
        $slide = $this->saveRecord($state, 'slides', $body, $id);
        $state['slideVersions'][] = $this->record(['slideId' => $slide['id'], 'version' => $version, 'document' => $slide['document'] ?? [], 'changeNote' => $current ? 'Im Slide-Editor gespeichert' : 'Slide angelegt']);
        return $slide;
    }

    private function berlinEventTime(string $date, string $time): ?string
    {
        $digits = preg_replace('/\D/', '', $date) ?: '';
        if (strlen($digits) < 8 || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) return null;
        $value = \DateTimeImmutable::createFromFormat('!Ymd H:i', substr($digits, 0, 8) . ' ' . $time, new \DateTimeZone('Europe/Berlin'));
        return $value ? $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z') : null;
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $body @return array<string,mixed> */
    private function saveRecord(array &$state, string $collection, array $body, ?string $id = null): array
    {
        $current = $id ? $this->required($state[$collection], $id, ucfirst($collection)) : [];
        $record = $this->record(array_replace($current, $body), $id);
        $index = $this->index($state[$collection], $record['id']);
        if ($index === null) $state[$collection][] = $record; else $state[$collection][$index] = $record;
        $this->audit($state, $collection . '.saved', (string)$record['id']);
        return $record;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function record(array $input, ?string $id = null): array
    {
        $now = $this->now();
        return array_replace(['id' => $id ?: $this->uuid(), 'createdAt' => $now], $input, ['id' => $id ?: ($input['id'] ?? $this->uuid()), 'updatedAt' => $now]);
    }

    /** @param array<string,mixed> $state @return array{0:int,1:array<string,mixed>,2:bool} */
    private function trashRecord(array &$state, string $collection, string $type, string $id): array
    {
        $record = $this->required($state[$collection], $id, ucfirst($type));
        $record['deletedAt'] = $this->now();
        $this->saveRecord($state, $collection, $record, $id);
        $state['trash'] = array_values(array_filter($state['trash'], fn(array $item): bool => !($item['type'] === $type && $item['id'] === $id)));
        $state['trash'][] = ['type' => $type, 'id' => $id, 'name' => $record['name'] ?? $record['title'] ?? $type, 'deletedAt' => $record['deletedAt'], 'deletedBy' => $this->identity['id']];
        return $this->ok(['ok' => true], true);
    }

    /** @param array<string,mixed> $state @return array{0:int,1:array<string,mixed>,2:bool} */
    private function restoreTrash(array &$state, string $collection, string $type, string $id): array
    {
        $record = $this->required($state[$collection], $id, ucfirst($type));
        $record['deletedAt'] = null;
        $this->saveRecord($state, $collection, $record, $id);
        $state['trash'] = array_values(array_filter($state['trash'], fn(array $item): bool => !($item['type'] === $type && $item['id'] === $id)));
        return $this->ok(['ok' => true], true);
    }

    /** @param array<string,mixed> $state @return array{0:int,1:array<string,mixed>,2:bool} */
    private function purgeTrash(array &$state, string $collection, string $type, string $id): array
    {
        $this->purge($state[$collection], $id);
        $state['trash'] = array_values(array_filter($state['trash'], fn(array $item): bool => !($item['type'] === $type && $item['id'] === $id)));
        return $this->ok(['ok' => true], true);
    }

    /** @param array<string,mixed> $state @return array{0:int,1:array<string,mixed>,2:bool} */
    private function deleteRecord(array &$state, string $collection, string $id): array
    {
        $this->purge($state[$collection], $id);
        return $this->ok(['ok' => true], true);
    }

    /** @param array<string,mixed> $state @return array{0:int,1:array<string,mixed>,2:bool} */
    private function restoreRecord(array &$state, string $collection, string $id): array
    {
        $record = $this->required($state[$collection], $id, ucfirst($collection));
        $record['deletedAt'] = null;
        $this->saveRecord($state, $collection, $record, $id);
        return $this->ok(['ok' => true], true);
    }

    /** @param array<string,mixed> $state @return array{0:int,1:array<string,mixed>,2:bool} */
    private function purgeRecord(array &$state, string $collection, string $id): array
    {
        $this->purge($state[$collection], $id);
        return $this->ok(['ok' => true], true);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function purge(array &$items, string $id): void
    {
        $before = count($items);
        $items = array_values(array_filter($items, fn(array $item): bool => $item['id'] !== $id));
        if (count($items) === $before) throw new RuntimeException('Element wurde nicht gefunden.', 404);
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    private function active(array $items): array
    {
        return array_values(array_filter($items, fn(array $item): bool => empty($item['deletedAt']) && ($item['status'] ?? '') !== 'archived'));
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,mixed> */
    private function required(array $items, string $id, string $label): array
    {
        $item = $this->first($items, fn(array $candidate): bool => $candidate['id'] === $id);
        if (!$item) throw new RuntimeException($label . ' wurde nicht gefunden.', 404);
        return $item;
    }

    /** @param array<int,array<string,mixed>> $items */
    private function index(array $items, string $id): ?int
    {
        foreach ($items as $index => $item) if (($item['id'] ?? '') === $id) return $index;
        return null;
    }

    /** @param array<int,array<string,mixed>> $items @param callable(array<string,mixed>):bool $predicate @return array<string,mixed>|null */
    private function first(array $items, callable $predicate): ?array
    {
        foreach ($items as $item) if ($predicate($item)) return $item;
        return null;
    }

    /** @param array<string,mixed> $state */
    private function audit(array &$state, string $action, string $entityId): void
    {
        $state['audit'][] = ['id' => $this->uuid(), 'actorType' => 'cms_user', 'actorId' => $this->identity['id'], 'action' => $action, 'entityId' => $entityId, 'createdAt' => $this->now()];
        $state['audit'] = array_slice($state['audit'], -500);
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return [
            'id' => $this->identity['id'],
            'username' => $this->identity['email'],
            'email' => $this->identity['email'],
            'displayName' => $this->identity['name'],
            'role' => in_array($this->identity['role'], ['admin', 'editor', 'viewer'], true) ? $this->identity['role'] : 'viewer',
            'status' => 'active',
            'mustChangePassword' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        $stored = $this->store->load();
        if (!array_key_exists('titleExclusions', $stored)) {
            $stored['titleExclusions'] = $stored['crewbrain']['config']['titleExclusions'] ?? [];
        }
        return array_replace_recursive($this->defaults(), $stored);
    }

    /** @return array<int,string> */
    private function normalizeNavigationOrder(mixed $value): array
    {
        $defaults = ['dashboard', 'events', 'displays', 'channels', 'media', 'schedule', 'operations', 'integrations', 'settings', 'users'];
        $requested = is_array($value) ? array_values(array_filter(array_map('strval', $value), static fn(string $key): bool => in_array($key, $defaults, true))) : [];
        return array_values(array_unique(array_merge($requested, $defaults)));
    }

    /** @return array<string,mixed> */
    private function defaults(): array
    {
        $now = $this->now();
        return [
            'schemaVersion' => 1, 'createdAt' => $now, 'updatedAt' => $now,
            'events' => [], 'eventSchedules' => [], 'slides' => [], 'slideVersions' => [], 'channels' => [], 'templates' => [],
            'mediaFolders' => [], 'mediaAssets' => [], 'presets' => [], 'displays' => [], 'groups' => [],
            'locations' => [], 'matrices' => [], 'schedules' => [], 'scheduleTargetOrder' => [], 'trash' => [],
            'warnings' => [], 'proof' => [], 'commands' => [], 'assignments' => [], 'presetExecutions' => [], 'playerPairings' => [], 'audit' => [], 'dwd' => [],
            'featureSettings' => ['dashboard' => true, 'events' => true, 'displays' => true, 'channels' => true, 'media' => true, 'schedule' => true, 'operations' => true, 'integrations' => true, 'settings' => true, 'users' => true, 'content' => true, 'presets_warnings' => true],
            'navigationOrder' => ['dashboard', 'events', 'displays', 'channels', 'media', 'schedule', 'operations', 'integrations', 'settings', 'users'],
            'publicCalendarSettings' => PublicEventCalendar::DEFAULTS,
            'activeEventSource' => null,
            'portableUsers' => [], 'portableApiUsers' => [],
            'titleExclusions' => [],
            'warningTemplates' => [[
                'id' => 'warning-template-info', 'name' => 'Wichtige Information', 'warningType' => 'info',
                'description' => 'Allgemeiner Hinweis', 'priority' => 700, 'status' => 'active',
                'document' => ['title' => 'Wichtige Information', 'instructions' => 'Bitte beachten Sie die Hinweise.', 'backgroundColor' => '#17342b', 'textColor' => '#ffffff'],
                'createdAt' => $now, 'updatedAt' => $now,
            ]],
            'crewbrain' => [
                'config' => ['baseUrl' => '', 'documentationUrl' => '', 'authType' => 'api_key', 'syncEnabled' => false, 'syncIntervalMinutes' => 1440, 'autoImportTime' => '03:00', 'lookbackDays' => 30, 'lookaheadDays' => 365, 'pageSize' => 100, 'importStrategy' => 'project', 'titleExclusions' => [], 'autoCreateChannels' => false, 'autoCreateSlides' => false],
                'mapping' => CrewBrainService::DEFAULT_MAPPING,
                'syncLogs' => [],
            ],
            'easyjob' => [
                'config' => ['baseUrl' => '', 'allowInsecureHttp' => false, 'allowSelfSignedCertificate' => false, 'syncEnabled' => false, 'autoImportTime' => '03:00', 'lookbackDays' => 30, 'lookaheadDays' => 365, 'pageSize' => 100, 'importMode' => 'projects_with_jobs', 'autoCreateChannels' => true],
                'mapping' => EasyJobService::DEFAULT_MAPPING,
                'syncLogs' => [],
            ],
        ];
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function integrationSources(array $state): array
    {
        $active = in_array($state['activeEventSource'] ?? null, ['crewbrain', 'easyjob'], true) ? $state['activeEventSource'] : null;
        return ['activeSource' => $active, 'sources' => [
            'crewbrain' => ['enabled' => $active === 'crewbrain', 'configured' => !empty($state['crewbrain']['config']['encryptedCredential'])],
            'easyjob' => ['enabled' => $active === 'easyjob', 'configured' => !empty($state['easyjob']['config']['encryptedCredentials'])],
        ]];
    }

    /** @param array<string,mixed> $state @return array<int,string> */
    private function titleExclusions(array $state): array
    {
        return $this->normalizeTitleExclusions($state['titleExclusions'] ?? ($state['crewbrain']['config']['titleExclusions'] ?? []));
    }

    /** @return array<int,string> */
    private function normalizeTitleExclusions(mixed $value): array
    {
        $entries = is_array($value) ? $value : [];
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn(mixed $entry): string => trim(is_string($entry) ? $entry : ''),
            $entries
        ))));
        if (count($normalized) > 100 || array_filter($normalized, static fn(string $entry): bool => strlen($entry) > 80)) {
            throw new RuntimeException('Die Titel-Ausschlussliste darf höchstens 100 Einträge mit jeweils 80 Zeichen enthalten.', 422);
        }
        return $normalized;
    }

    /** @param array<int,string> $exclusions */
    private function matchingTitleExclusion(string $title, array $exclusions): ?string
    {
        foreach ($exclusions as $exclusion) if (str_contains($title, $exclusion)) return $exclusion;
        return null;
    }

    /** @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>,2:bool} */
    private function ok(array $body, bool $changed = false): array { return [200, $body, $changed]; }
    /** @param array<string,mixed> $body @return array{0:int,1:array<string,mixed>,2:bool} */
    private function created(array $body): array { return [201, $body, true]; }
    private function now(): string { return gmdate('c'); }
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
