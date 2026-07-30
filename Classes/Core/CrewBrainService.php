<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;
use Throwable;

final class CrewBrainService
{
    public const CONFIGURATION_FORMAT = 'kiosky-crewbrain-configuration';
    public const CONFIGURATION_VERSION = 1;

    /** @var array<string,string> */
    public const DEFAULT_MAPPING = [
        'title' => 'TitelExport',
        'description' => 'PublicDescription',
        'date' => 'DateFrom',
        'admissionStart' => 'Admission',
        'eventStart' => 'Start',
        'breakStart' => 'BreakTimestamp',
        'eventEnd' => 'DateUntil',
        'venue' => 'Location.Description',
        'ticketUrl' => 'TicketURL',
        'remainingTickets' => 'TicketStatus',
    ];

    /** @var array<string,array<int,string>> */
    private const ALLOWED_MAPPING = [
        'title' => ['TitelExport', 'Title', 'EventIDFormatted'],
        'description' => ['PublicDescription', 'Description', 'ExternalDescription', '__none'],
        'date' => ['DateFrom', 'DateUntil'],
        'admissionStart' => ['Admission', 'Start', '__none'],
        'eventStart' => ['Start', 'DateFrom'],
        'breakStart' => ['BreakTimestamp', '__none'],
        'eventEnd' => ['DateUntil', '__none'],
        'venue' => ['Location.Description', 'Location.City', 'Client.Description', '__none'],
        'ticketUrl' => ['TicketURL', '__none'],
        'remainingTickets' => ['TicketStatus', '__none'],
    ];

    public function __construct(
        private readonly HttpClient $http,
        private readonly Crypto $crypto,
    ) {
    }

    /** @param array<string,mixed> $stored @return array<string,mixed> */
    public function publicConfig(array $stored): array
    {
        $config = $stored;
        $encrypted = (string)($config['encryptedCredential'] ?? '');
        unset($config['encryptedCredential']);
        $config['hasCredential'] = $encrypted !== '';
        if ($encrypted !== '') {
            try {
                $this->crypto->decrypt($encrypted);
            } catch (Throwable) {
                $config['hasCredential'] = false;
                $config['credentialUnreadable'] = true;
            }
        }
        return $config;
    }

    /** @param array<string,mixed> $stored @param array<string,mixed> $mapping @return array<string,mixed> */
    public function exportConfiguration(array $stored, array $mapping): array
    {
        $encrypted = (string)($stored['encryptedCredential'] ?? '');
        if ($encrypted === '') throw new RuntimeException('Die CrewBrain-Konfiguration enthält keinen exportierbaren Zugang.', 422);
        $credential = $this->crypto->decrypt($encrypted);
        $connection = [];
        foreach ([
            'baseUrl', 'documentationUrl', 'authType', 'syncEnabled', 'syncIntervalMinutes',
            'autoImportTime', 'lookbackDays', 'lookaheadDays', 'pageSize', 'importStrategy',
            'titleExclusions', 'autoCreateChannels', 'autoCreateSlides',
        ] as $field) {
            if (array_key_exists($field, $stored)) $connection[$field] = $stored[$field];
        }
        $connection['credential'] = $credential;
        return [
            'format' => self::CONFIGURATION_FORMAT,
            'version' => self::CONFIGURATION_VERSION,
            'exportedAt' => gmdate('c'),
            'connection' => $connection,
            'fieldMapping' => $this->validateMapping($mapping),
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $payload
     * @return array{config:array<string,mixed>,mapping:array<string,string>}
     */
    public function importConfiguration(array $current, array $payload): array
    {
        if (($payload['format'] ?? null) !== self::CONFIGURATION_FORMAT || ($payload['version'] ?? null) !== self::CONFIGURATION_VERSION) {
            throw new RuntimeException('Die Datei ist keine unterstützte Kiosky-CrewBrain-Konfiguration.', 422);
        }
        $connection = is_array($payload['connection'] ?? null) ? $payload['connection'] : null;
        $mapping = is_array($payload['fieldMapping'] ?? null) ? $payload['fieldMapping'] : null;
        if ($connection === null) throw new RuntimeException('In der Importdatei fehlt die CrewBrain-Verbindung.', 422);
        if ($mapping === null) throw new RuntimeException('In der Importdatei fehlen die CrewBrain-Feldzuordnungen.', 422);
        $credential = is_array($connection['credential'] ?? null) ? array_filter(
            $connection['credential'],
            static fn(mixed $value): bool => is_string($value) && $value !== ''
        ) : [];
        if (!$credential) throw new RuntimeException('In der Importdatei fehlt der CrewBrain-Zugang.', 422);
        return [
            'config' => $this->saveConfig($current, $connection),
            'mapping' => $this->validateMapping($mapping),
        ];
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $input @return array<string,mixed> */
    public function saveConfig(array $current, array $input): array
    {
        $baseUrl = $this->httpsUrl($input['baseUrl'] ?? null, 'Basis-URL');
        $documentationUrl = $this->httpsUrl($input['documentationUrl'] ?? $baseUrl, 'Dokumentations-URL');
        $authType = (string)($input['authType'] ?? 'api_key');
        if (!in_array($authType, ['api_key', 'bearer', 'oauth2', 'basic'], true)) {
            throw new RuntimeException('Nicht unterstützte CrewBrain-Authentifizierungsart.', 422);
        }
        $exclusions = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => trim(is_string($value) ? $value : ''),
            is_array($input['titleExclusions'] ?? null) ? $input['titleExclusions'] : []
        ))));
        if (count($exclusions) > 100 || array_filter($exclusions, static fn(string $value): bool => strlen($value) > 80)) {
            throw new RuntimeException('Die Titel-Ausschlussliste darf höchstens 100 Einträge mit jeweils 80 Zeichen enthalten.', 422);
        }
        $autoImportTime = (string)($input['autoImportTime'] ?? '03:00');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $autoImportTime)) {
            throw new RuntimeException('Die Uhrzeit für den automatischen Import ist ungültig.', 422);
        }
        $strategy = (string)($input['importStrategy'] ?? 'project');
        if (!in_array($strategy, ['project', 'job', 'project_with_jobs'], true)) $strategy = 'project';
        $credential = is_array($input['credential'] ?? null) ? array_filter(
            $input['credential'],
            static fn(mixed $value): bool => is_string($value) && $value !== ''
        ) : null;
        return array_replace($current, [
            'id' => (string)($current['id'] ?? $this->uuid()),
            'baseUrl' => $baseUrl,
            'documentationUrl' => $documentationUrl,
            'authType' => $authType,
            'syncEnabled' => (bool)($input['syncEnabled'] ?? false),
            'syncIntervalMinutes' => $this->integer($input['syncIntervalMinutes'] ?? 1440, 1, 1440, 'Synchronisationsintervall'),
            'autoImportTime' => $autoImportTime,
            'lookbackDays' => $this->integer($input['lookbackDays'] ?? 30, 0, 3650, 'Rückblick'),
            'lookaheadDays' => $this->integer($input['lookaheadDays'] ?? 365, 1, 3650, 'Vorausplanung'),
            'pageSize' => $this->integer($input['pageSize'] ?? 100, 1, 100, 'Seitengröße'),
            'importStrategy' => $strategy,
            'titleExclusions' => $exclusions,
            'autoCreateChannels' => (bool)($input['autoCreateChannels'] ?? false),
            'autoCreateSlides' => (bool)($input['autoCreateSlides'] ?? false),
            'updatedAt' => gmdate('c'),
            ...($credential ? ['encryptedCredential' => $this->crypto->encrypt($credential)] : []),
        ]);
    }

    /** @param array<string,mixed> $input */
    public function requestAccessToken(array $input): string
    {
        $baseUrl = $this->httpsUrl($input['baseUrl'] ?? null, 'Basis-URL');
        $host = (string)parse_url($baseUrl, PHP_URL_HOST);
        if ($host !== 'crewbrain.com' && !str_ends_with($host, '.crewbrain.com')) {
            throw new RuntimeException('Der automatische Login ist ausschließlich für HTTPS-Mandanten unter crewbrain.com erlaubt.', 422);
        }
        $username = trim((string)($input['username'] ?? ''));
        $password = (string)($input['password'] ?? '');
        if ($username === '' || $password === '') throw new RuntimeException('CrewBrain-Benutzername und Passwort werden benötigt.', 422);
        $url = 'https://' . $host . '/api/accesstoken';
        $response = $this->http->request('GET', $url, [
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
        ]);
        if (in_array($response->status, [401, 403], true)) throw new RuntimeException('CrewBrain hat Benutzername oder Passwort abgelehnt.', 401);
        if ($response->status < 200 || $response->status >= 300) {
            throw new RuntimeException('CrewBrain konnte keinen Access-Token erstellen (HTTP ' . $response->status . ').', 502);
        }
        $payload = $response->json();
        foreach (['Accesstoken', 'accessToken', 'access_token', 'token'] as $key) {
            if (is_string($payload[$key] ?? null) && $payload[$key] !== '') return $payload[$key];
        }
        throw new RuntimeException('In der CrewBrain-Antwort wurde kein Access-Token gefunden.', 502);
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function test(array $config): array
    {
        $checkedAt = gmdate('c');
        $permissions = ['identity' => false, 'events' => false, 'rights' => false];
        try {
            $this->request($config, 'me');
            $permissions['identity'] = true;
            $this->request($config, 'me/rights');
            $permissions['rights'] = true;
            $this->request($config, 'events', ['limit' => 1, 'offset' => 0, 'fields' => 'ID,Type,Subtype,Title']);
            $permissions['events'] = true;
            return ['ok' => true, 'apiVersion' => 'API v2', 'permissions' => $permissions, 'checkedAt' => $checkedAt];
        } catch (Throwable $error) {
            return [
                'ok' => false,
                'apiVersion' => 'API v2',
                'permissions' => $permissions,
                'checkedAt' => $checkedAt,
                'error' => ['code' => $this->errorCode($error), 'message' => $error->getMessage()],
            ];
        }
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $options @return array<string,mixed> */
    public function listEvents(array $config, array $options): array
    {
        $limit = min(max((int)($options['limit'] ?? $config['pageSize'] ?? 100), 1), 100);
        $offset = max((int)($options['offset'] ?? 0), 0);
        $strategy = (string)($config['importStrategy'] ?? 'project');
        $query = [
            'limit' => $limit,
            'offset' => $offset,
            'sort' => 'DateFrom',
            'expand' => 'Location,Client,AdditionalDatas',
            ...($strategy !== 'project' ? ['includeSubevents' => 'true'] : []),
            ...(is_string($options['from'] ?? null) && $options['from'] !== '' ? ['DateUntil[gte]' => $options['from']] : []),
            ...(is_string($options['until'] ?? null) && $options['until'] !== '' ? ['DateFrom[lte]' => $options['until']] : []),
            ...(is_string($options['status'] ?? null) && $options['status'] !== '' ? ['Status' => $options['status']] : []),
            ...(is_string($options['search'] ?? null) && $options['search'] !== '' ? ['search' => $options['search']] : []),
        ];
        $payload = $this->request($config, 'events', $query);
        $events = is_array($payload['data'] ?? null) ? array_values(array_filter(
            $payload['data'],
            fn(mixed $event): bool => is_array($event) && $this->matchesStrategy($event, $strategy)
        )) : null;
        if ($events === null) throw new RuntimeException('CrewBrain hat für die Veranstaltungsabfrage keine gültige Datenliste geliefert.', 502);
        return [
            'data' => $events,
            'itemCount' => count($events),
            'totalItems' => (int)($payload['totalItems'] ?? count($events)),
            'limit' => (int)($payload['limit'] ?? $limit),
            'offset' => (int)($payload['offset'] ?? $offset),
        ];
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function event(array $config, string $id): array
    {
        return $this->request($config, 'events/' . rawurlencode($id), ['expand' => 'Location,Client,AdditionalDatas']);
    }

    /** @param array<string,mixed> $event @param array<string,string> $mapping @return array<string,mixed> */
    public function mapEvent(array $event, array $mapping): array
    {
        $mapping = array_replace(self::DEFAULT_MAPPING, $mapping);
        $mappedDate = $this->path($event, $mapping['date']);
        $title = $this->path($event, $mapping['title']) ?: (string)($event['TitelExport'] ?? $event['Title'] ?? '');
        if ($title === '') throw new RuntimeException('CrewBrain-Veranstaltung enthält keinen Titel.', 422);
        $record = array_filter([
            'sourceId' => 'source-crewbrain',
            'externalId' => (string)($event['ID'] ?? ''),
            'externalParentId' => !empty($event['ParentID']) ? (string)$event['ParentID'] : null,
            'externalObjectType' => (string)($event['Subtype'] ?? $event['Type'] ?? ''),
            'externalNumber' => (string)($event['EventIDFormatted'] ?? $event['EventIDManual'] ?? ''),
            'title' => $title,
            'description' => $this->path($event, $mapping['description']),
            'date' => $mappedDate !== '' ? substr($mappedDate, 0, 8) : null,
            'admissionStart' => $this->dateAndTime($mappedDate, $this->path($event, $mapping['admissionStart'])),
            'eventStart' => $this->dateAndTime($mappedDate, $this->path($event, $mapping['eventStart'])) ?: ($mappedDate ?: null),
            'breakStart' => $this->dateAndTime($mappedDate, $this->path($event, $mapping['breakStart'])),
            'eventEnd' => $this->path($event, $mapping['eventEnd']) ?: null,
            'venue' => $this->path($event, $mapping['venue']) ?: null,
            'status' => !empty($event['Cancelled']) ? 'cancelled' : (!empty($event['Completed']) ? 'ended' : (($event['TicketStatus'] ?? '') === 'SOLD_OUT' ? 'sold_out' : (!empty($event['Public']) ? 'published' : 'imported'))),
            'publicNotes' => is_string($event['PublicDescription'] ?? null) ? $event['PublicDescription'] : null,
            'ticketUrl' => $this->path($event, $mapping['ticketUrl']) ?: null,
            'remainingTickets' => $this->path($event, $mapping['remainingTickets']) ?: null,
            'sourceCreatedAt' => is_string($event['CreatedDate'] ?? null) ? $event['CreatedDate'] : null,
            'sourceUpdatedAt' => is_string($event['ChangedDate'] ?? null) ? $event['ChangedDate'] : null,
            'lastSyncedAt' => gmdate('c'),
            'syncStatus' => 'synced',
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
        $record['importHash'] = hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $record;
    }

    /** @param array<string,mixed> $event @param array<int,string> $exclusions */
    public function titleExclusion(array $event, string $mappedTitle, array $exclusions): ?string
    {
        $titles = array_filter([$mappedTitle, $event['TitelExport'] ?? null, $event['Title'] ?? null], 'is_string');
        foreach ($exclusions as $exclusion) {
            foreach ($titles as $title) if (str_contains($title, $exclusion)) return $exclusion;
        }
        return null;
    }

    /** @param array<string,mixed> $input @return array<string,string> */
    public function validateMapping(array $input): array
    {
        $mapping = [];
        foreach (self::DEFAULT_MAPPING as $target => $fallback) {
            $selected = is_string($input[$target] ?? null) ? $input[$target] : $fallback;
            if (!in_array($selected, self::ALLOWED_MAPPING[$target], true)) {
                throw new RuntimeException('Ungültige CrewBrain-Feldzuordnung für ' . $target . '.', 422);
            }
            $mapping[$target] = $selected;
        }
        return $mapping;
    }

    /** @param array<string,mixed> $config @param array<string,string|int|bool> $query @return array<string,mixed> */
    private function request(array $config, string $path, array $query = []): array
    {
        $baseUrl = $this->httpsUrl($config['baseUrl'] ?? null, 'Basis-URL');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query) $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $headers = ['Accept' => 'application/json', ...$this->authHeaders($config)];
        $response = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->http->request('GET', $url, $headers, null, 15);
            if (!($response->status === 429 || $response->status >= 500) || $attempt === 2) break;
            $retryAfter = max(0, (int)$response->header('Retry-After'));
            usleep(($retryAfter > 0 ? min($retryAfter, 3) * 1_000_000 : 250_000 * (2 ** $attempt)));
        }
        if (!$response) throw new RuntimeException('CrewBrain konnte nicht erreicht werden.', 502);
        $this->assertStatus($response);
        return $response->json();
    }

    /** @param array<string,mixed> $config @return array<string,string> */
    private function authHeaders(array $config): array
    {
        $encrypted = (string)($config['encryptedCredential'] ?? '');
        if ($encrypted === '') throw new RuntimeException('Es ist kein CrewBrain-Zugang hinterlegt.', 422);
        $credential = $this->crypto->decrypt($encrypted);
        return match ((string)($config['authType'] ?? 'api_key')) {
            'api_key' => ['X-API-KEY' => (string)($credential['apiKey'] ?? '')],
            'basic' => ['Authorization' => 'Basic ' . base64_encode((string)($credential['username'] ?? '') . ':' . (string)($credential['password'] ?? ''))],
            'oauth2' => ['Authorization' => 'Bearer ' . (string)($credential['accessToken'] ?? $credential['token'] ?? '')],
            default => ['Authorization' => 'Bearer ' . (string)($credential['token'] ?? '')],
        };
    }

    private function assertStatus(HttpResponse $response): void
    {
        if ($response->status >= 200 && $response->status < 300) return;
        $detail = '';
        try {
            $payload = $response->json();
            foreach (['message', 'error_description', 'error', 'detail'] as $key) {
                if (is_string($payload[$key] ?? null)) {
                    $detail = trim(preg_replace('/\s+/', ' ', $payload[$key]) ?? '');
                    break;
                }
            }
        } catch (Throwable) {
        }
        $message = match ($response->status) {
            401 => 'CrewBrain hat die Zugangsdaten abgelehnt.',
            403 => 'Der CrewBrain-Benutzer hat nicht die benötigten Leserechte.',
            404 => 'Der CrewBrain-Endpunkt oder Datensatz wurde nicht gefunden.',
            429 => 'Das CrewBrain-Rate-Limit wurde erreicht.',
            default => $response->status >= 500 ? 'CrewBrain meldet einen Serverfehler.' : 'Unerwartete CrewBrain-Antwort (HTTP ' . $response->status . ').',
        };
        if ($detail !== '' && !in_array($response->status, [401, 403], true)) $message .= ' ' . substr($detail, 0, 240);
        throw new RuntimeException($message, in_array($response->status, [401, 403, 404, 429], true) ? $response->status : 502);
    }

    /** @param array<string,mixed> $event */
    private function matchesStrategy(array $event, string $strategy): bool
    {
        if ($strategy === 'job') return ($event['Type'] ?? '') === 'JOB';
        if ($strategy === 'project_with_jobs') return ($event['Type'] ?? '') === 'PROJECT' || in_array($event['Subtype'] ?? '', ['MAINJOB', 'SUBJOB'], true);
        return ($event['Type'] ?? '') === 'PROJECT' || ($event['Subtype'] ?? '') === 'PROJECT';
    }

    /** @param array<string,mixed> $event */
    private function path(array $event, string $path): string
    {
        if ($path === '__none') return '';
        $value = $event;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return '';
            $value = $value[$segment];
        }
        return is_string($value) || is_numeric($value) ? (string)$value : '';
    }

    private function dateAndTime(string $date, string $time): ?string
    {
        if ($date === '') return null;
        if ($time === '') return $date;
        $datePart = substr(preg_replace('/\D/', '', $date) ?? '', 0, 8);
        $timePart = str_pad(substr(preg_replace('/\D/', '', $time) ?? '', 0, 6), 6, '0');
        return $datePart !== '' ? $datePart . 'T' . $timePart . 'Z' : null;
    }

    private function httpsUrl(mixed $value, string $label): string
    {
        $url = rtrim(trim(is_string($value) ? $value : ''), '/');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException($label . ' muss eine gültige HTTPS-URL sein.', 422);
        }
        return $url;
    }

    private function integer(mixed $value, int $minimum, int $maximum, string $label): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $minimum || $number > $maximum) {
            throw new RuntimeException($label . ' muss zwischen ' . $minimum . ' und ' . $maximum . ' liegen.', 422);
        }
        return $number;
    }

    private function errorCode(Throwable $error): string
    {
        return match ($error->getCode()) {
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            429 => 'RATE_LIMIT',
            422 => 'INVALID_RESPONSE',
            default => 'NETWORK',
        };
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
