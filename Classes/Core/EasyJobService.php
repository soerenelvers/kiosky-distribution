<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;
use Throwable;

final class EasyJobService
{
    public const DEFAULT_MAPPING = [
        'title' => 'Caption',
        'subtitle' => '__none',
        'description' => 'Description',
        'date' => 'StartDate',
        'admissionStart' => 'AdmissionStart',
        'eventStart' => 'StartDate',
        'breakStart' => '__none',
        'eventEnd' => 'EndDate',
        'venue' => 'Address_Venue.Company',
        'room' => 'Room',
        'publicNotes' => 'Comment',
        'ticketUrl' => 'TicketURL',
        'remainingTickets' => 'TicketStatus',
    ];

    private const MAPPING_PATHS = [
        'title' => ['Caption', 'EventName', 'Number', 'CustomNumber', '__project.Caption'],
        'subtitle' => ['ProjectState.Caption', 'JobState.Caption', 'Address_Customer.Company', '__project.Caption', '__none'],
        'description' => ['Description', 'Comment', 'Info', 'PublicInfo', '__none'],
        'date' => ['StartDate', 'EventStart', 'Start', 'JobStart', '__project.StartDate'],
        'admissionStart' => ['Admission', 'AdmissionStart', 'DoorsOpen', '__none'],
        'eventStart' => ['StartDate', 'EventStart', 'Start', 'JobStart', '__project.StartDate'],
        'breakStart' => ['BreakStart', 'PauseStart', '__none'],
        'eventEnd' => ['EndDate', 'EventEnd', 'End', 'JobEnd', '__project.EndDate', '__none'],
        'venue' => ['Address_Venue.Company', 'Address_Venue.City', 'Venue.Caption', 'Location.Caption', 'Stock.Caption', 'Address_Customer.City', '__none'],
        'room' => ['Room', 'Location.Caption', 'Stock.Caption', '__none'],
        'publicNotes' => ['PublicInfo', 'Comment', 'Info', 'Description', '__none'],
        'ticketUrl' => ['TicketURL', 'TicketUrl', '__none'],
        'remainingTickets' => ['TicketStatus', 'RemainingTickets', '__none'],
    ];

    public function __construct(private readonly HttpClient $http, private readonly Crypto $crypto)
    {
    }

    /** @param array<string,mixed> $stored @return array<string,mixed> */
    public function publicConfig(array $stored): array
    {
        $config = $stored;
        $encrypted = (string)($config['encryptedCredentials'] ?? '');
        unset($config['encryptedCredentials']);
        $config['hasCredential'] = $encrypted !== '';
        return $config;
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $input @return array<string,mixed> */
    public function saveConfig(array $current, array $input): array
    {
        $url = trim((string)($input['baseUrl'] ?? ''));
        $url = preg_replace('#/(?:token|api\.json(?:/.*)?)/*$#i', '', $url) ?: $url;
        $url = rtrim($url, '/') . '/';
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)) throw new RuntimeException('Die easyjob-Serveradresse muss HTTP oder HTTPS verwenden.', 422);
        $allowInsecureHttp = (bool)($input['allowInsecureHttp'] ?? false);
        $allowSelfSignedCertificate = (bool)($input['allowSelfSignedCertificate'] ?? false);
        $local = in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
        if (($parts['scheme'] ?? '') === 'http' && !$local && !$allowInsecureHttp) {
            throw new RuntimeException('Die easyjob-Adresse verwendet unverschlüsseltes HTTP. Aktiviere dies nur für ein geschütztes internes Netzwerk.', 422);
        }
        $mode = (string)($input['importMode'] ?? 'projects_with_jobs');
        if (!in_array($mode, ['projects', 'jobs', 'projects_with_jobs'], true)) throw new RuntimeException('Der easyjob-Importmodus ist ungültig.', 422);
        $credentials = null;
        if (trim((string)($input['username'] ?? '')) !== '' || (string)($input['password'] ?? '') !== '') {
            if (trim((string)($input['username'] ?? '')) === '' || (string)($input['password'] ?? '') === '') throw new RuntimeException('Benutzername und Passwort werden gemeinsam benötigt.', 422);
            $credentials = ['username' => trim((string)$input['username']), 'password' => (string)$input['password']];
        }
        return array_replace($current, [
            'id' => (string)($current['id'] ?? $this->uuid()), 'baseUrl' => $url,
            'allowInsecureHttp' => $allowInsecureHttp,
            'allowSelfSignedCertificate' => $allowSelfSignedCertificate,
            'syncEnabled' => (bool)($input['syncEnabled'] ?? false), 'autoImportTime' => (string)($input['autoImportTime'] ?? '03:00'),
            'lookbackDays' => max(0, min(3650, (int)($input['lookbackDays'] ?? 30))),
            'lookaheadDays' => max(1, min(3650, (int)($input['lookaheadDays'] ?? 365))),
            'pageSize' => max(1, min(500, (int)($input['pageSize'] ?? 100))), 'importMode' => $mode,
            'autoCreateChannels' => ($input['autoCreateChannels'] ?? true) !== false, 'updatedAt' => gmdate('c'),
            ...($credentials ? ['encryptedCredentials' => $this->crypto->encrypt($credentials)] : []),
        ]);
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function test(array $config): array
    {
        try {
            $projects = $this->projects($config, ['style' => 'Compact']);
            return ['ok' => true, 'checkedAt' => gmdate('c'), 'projectsReadable' => true, 'projectCount' => count($projects), 'jobsReadable' => null];
        } catch (Throwable $error) {
            return ['ok' => false, 'checkedAt' => gmdate('c'), 'error' => ['code' => 'EASYJOB_CONNECTION_FAILED', 'message' => $error->getMessage()]];
        }
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $query @return array<int,array<string,mixed>> */
    public function projects(array $config, array $query): array
    {
        $payload = $this->request($config, 'api.json/Projects/List/', $query);
        foreach (['data', 'Data', 'Projects', 'Items', 'Result', 'Value', 'projects', 'items', 'result', 'value'] as $key) {
            if (is_array($payload[$key] ?? null)) return $this->unwrapListItems($payload[$key]);
        }
        if (array_is_list($payload)) return $this->unwrapListItems($payload);
        return [];
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function project(array $config, string $id): array { return $this->detailPayload($this->request($config, 'api.json/Projects/Details/' . rawurlencode($id)), ['Data', 'Project', 'Item', 'data', 'project', 'item']); }
    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function job(array $config, string $id): array { return $this->detailPayload($this->request($config, 'api.json/Job/Details/' . rawurlencode($id)), ['Data', 'Job', 'Item', 'data', 'job', 'item']); }

    /** @param array<string,mixed> $mapping @return array<string,string> */
    public function validateMapping(array $mapping): array
    {
        $validated = [];
        foreach (self::DEFAULT_MAPPING as $target => $fallback) {
            $selected = is_string($mapping[$target] ?? null) ? $mapping[$target] : $fallback;
            if (!in_array($selected, self::MAPPING_PATHS[$target], true)) throw new RuntimeException('Ungültige easyjob-Feldzuordnung für ' . $target . '.', 422);
            $validated[$target] = $selected;
        }
        return $validated;
    }

    /** @param array<string,mixed> $project @param array<string,mixed>|null $job @param array<string,string> $mapping @return array<string,mixed> */
    public function mapEvent(array $project, ?array $job = null, array $mapping = self::DEFAULT_MAPPING): array
    {
        $mapping = $this->validateMapping($mapping);
        $source = $job ?: $project;
        $projectId = $this->value($project, ['IdProject', 'ID', 'Id']);
        $jobId = $job ? $this->value($job, ['IdJob', 'ID', 'Id']) : '';
        if ($projectId === '') throw new RuntimeException('easyjob-Projekt ohne stabile IdProject kann nicht importiert werden.', 422);
        $start = $this->mappedValue($source, $project, $mapping['eventStart']);
        $date = $this->mappedValue($source, $project, $mapping['date']);
        $end = $this->mappedValue($source, $project, $mapping['eventEnd']);
        $externalId = $jobId !== '' ? 'job:' . $jobId : 'project:' . $projectId;
        $mapped = [
            'sourceId' => 'source-easyjob', 'externalId' => $externalId, 'externalParentId' => $jobId !== '' ? 'project:' . $projectId : null,
            'externalObjectType' => $jobId !== '' ? 'JOB' : 'PROJECT',
            'externalNumber' => $this->value($source, ['Number', 'CustomNumber']) ?: $this->value($project, ['Number', 'CustomNumber']),
            'title' => $this->mappedValue($source, $project, $mapping['title']) ?: ($this->value($source, ['EventName', 'Caption']) ?: ($this->value($project, ['Caption']) ?: 'easyjob ' . ($jobId !== '' ? 'Job ' . $jobId : 'Projekt ' . $projectId))),
            'subtitle' => $this->mappedValue($source, $project, $mapping['subtitle']) ?: null,
            'description' => $this->mappedValue($source, $project, $mapping['description']) ?: null,
            'date' => ($date ?: $start) !== '' && strtotime($date ?: $start) !== false ? gmdate('Ymd', (int)strtotime($date ?: $start)) : null,
            'eventStart' => $this->iso($start), 'eventEnd' => $this->iso($end),
            'admissionStart' => $this->iso($this->mappedValue($source, $project, $mapping['admissionStart'])),
            'breakStart' => $this->iso($this->mappedValue($source, $project, $mapping['breakStart'])),
            'venue' => $this->mappedValue($source, $project, $mapping['venue']) ?: null,
            'room' => $this->mappedValue($source, $project, $mapping['room']) ?: null,
            'publicNotes' => $this->mappedValue($source, $project, $mapping['publicNotes']) ?: null,
            'ticketUrl' => $this->mappedValue($source, $project, $mapping['ticketUrl']) ?: null,
            'remainingTickets' => $this->mappedValue($source, $project, $mapping['remainingTickets']) ?: null,
            'lastSyncedAt' => gmdate('c'), 'syncStatus' => 'synced',
        ];
        $mapped['importHash'] = hash('sha256', json_encode($mapped, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $mapped;
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $query @return array<string,mixed> */
    private function request(array $config, string $path, array $query = []): array
    {
        $credentials = $this->credentials($config);
        $verifyTls = empty($config['allowSelfSignedCertificate']);
        $tokenResponse = $this->http->request('POST', rtrim((string)$config['baseUrl'], '/') . '/token', ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'], http_build_query(['grant_type' => 'password'] + $credentials), 50, true, $verifyTls);
        if ($tokenResponse->status < 200 || $tokenResponse->status >= 300) throw new RuntimeException('easyjob hat die Zugangsdaten abgelehnt.', 401);
        $token = (string)($tokenResponse->json()['access_token'] ?? '');
        if ($token === '') throw new RuntimeException('easyjob hat keinen Access-Token geliefert.', 502);
        $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
        $url = rtrim((string)$config['baseUrl'], '/') . '/' . $path . ($query ? '?' . http_build_query($query) : '');
        $response = $this->http->request('GET', $url, ['Accept' => 'application/json; charset=utf-8', 'Content-Type' => 'application/json; charset=utf-8', 'Authorization' => 'Bearer ' . $token], null, 50, true, $verifyTls);
        if ($response->status < 200 || $response->status >= 300) throw new RuntimeException('easyjob meldet HTTP ' . $response->status . '.', $response->status >= 400 && $response->status < 600 ? $response->status : 502);
        return $response->json();
    }
    /** @param array<string,mixed> $payload @param array<int,string> $keys @return array<string,mixed> */
    private function detailPayload(array $payload, array $keys): array { foreach ($keys as $key) if (is_array($payload[$key] ?? null) && !array_is_list($payload[$key])) return $payload[$key]; return $payload; }
    /** @param array<mixed> $items @return array<int,array<string,mixed>> */
    private function unwrapListItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            foreach (['Project', 'Item', 'Data', 'project', 'item', 'data'] as $key) {
                if (is_array($item[$key] ?? null) && !array_is_list($item[$key])) {
                    $item = $item[$key];
                    break;
                }
            }
            $result[] = $item;
        }
        return $result;
    }
    /** @param array<string,mixed> $source @param array<string,mixed> $project */
    private function mappedValue(array $source, array $project, string $path): string
    {
        if ($path === '__none') return '';
        if (str_starts_with($path, '__project.')) return $this->value($project, [substr($path, strlen('__project.'))]);
        return $this->value($source, [$path]);
    }

    /** @param array<string,mixed> $config @return array{username:string,password:string} */
    private function credentials(array $config): array
    {
        $encrypted = (string)($config['encryptedCredentials'] ?? '');
        if ($encrypted === '') throw new RuntimeException('Für easyjob sind noch keine Zugangsdaten gespeichert.', 422);
        $credentials = $this->crypto->decrypt($encrypted);
        return ['username' => (string)($credentials['username'] ?? ''), 'password' => (string)($credentials['password'] ?? '')];
    }
    /** @param array<string,mixed> $source @param array<int,string> $paths */
    private function value(array $source, array $paths): string { foreach ($paths as $path) { $value=$source; foreach(explode('.',$path) as $key) $value=is_array($value)?($value[$key]??null):null; if(is_scalar($value)&&trim((string)$value)!=='') return trim((string)$value); } return ''; }
    private function iso(string $value): ?string { $time=strtotime($value); return $value!==''&&$time!==false?gmdate('c',$time):null; }
    private function uuid(): string { return bin2hex(random_bytes(16)); }
}
