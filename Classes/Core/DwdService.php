<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;

final class DwdService
{
    private const URL = 'https://www.dwd.de/DWD/warnungen/warnapp/json/warnings.json';

    public function __construct(private readonly HttpClient $http)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function warnings(string $warningAreaCode): array
    {
        $code = preg_replace('/\D/', '', $warningAreaCode) ?? '';
        if ($code === '') throw new RuntimeException('Für den Standort ist keine gültige DWD-WarnCellID hinterlegt.', 422);
        $response = $this->http->request('GET', self::URL, [
            'Accept' => 'application/json,text/plain;q=0.9',
            'User-Agent' => 'Kiosky/1.2 DWD warning integration',
        ], null, 12);
        if ($response->status < 200 || $response->status >= 300) {
            throw new RuntimeException('DWD-Warndienst antwortet mit HTTP ' . $response->status . '.', 502);
        }
        if (!preg_match('/^warnWetter\.loadWarnings\(([\s\S]+)\);\s*$/', trim($response->body), $match)) {
            throw new RuntimeException('Der DWD-Warndienst hat ein unerwartetes Datenformat geliefert.', 502);
        }
        try {
            $payload = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Der DWD-Warndienst hat ungültige Warndaten geliefert.', 502);
        }
        $all = is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [];
        $candidates = array_unique(array_filter([$code, strlen($code) > 9 ? substr($code, 0, 9) : $code]));
        $updatedAt = $this->timestamp($payload['time'] ?? null);
        $result = [];
        foreach ($all as $cellId => $entries) {
            if (!array_filter($candidates, static fn(string $candidate): bool => $cellId === $candidate || str_starts_with($candidate, (string)$cellId))) continue;
            if (!is_array($entries)) continue;
            foreach ($entries as $entry) {
                if (!is_array($entry)) continue;
                $event = (string)($entry['event'] ?? 'Wetterwarnung');
                $startsAt = $this->timestamp($entry['start'] ?? null);
                $digest = substr(hash('sha256', $cellId . '|' . $event . '|' . ($startsAt ?? '') . '|' . (string)($entry['headline'] ?? '')), 0, 24);
                $result[] = array_filter([
                    'externalId' => 'dwd-' . $digest,
                    'source' => 'dwd',
                    'warningType' => $event,
                    'severity' => max(0, min(5, (int)round((float)($entry['level'] ?? 1)))),
                    'title' => (string)($entry['headline'] ?? $event),
                    'description' => is_string($entry['description'] ?? null) ? $entry['description'] : null,
                    'instructions' => is_string($entry['instruction'] ?? null) ? $entry['instruction'] : null,
                    'areaName' => is_string($entry['regionName'] ?? null) ? $entry['regionName'] : null,
                    'startsAt' => $startsAt,
                    'endsAt' => $this->timestamp($entry['end'] ?? null),
                    'updatedAt' => $updatedAt,
                ], static fn(mixed $value): bool => $value !== null && $value !== '');
            }
        }
        usort($result, static fn(array $left, array $right): int => $right['severity'] <=> $left['severity']);
        return $result;
    }

    private function timestamp(mixed $value): ?string
    {
        if (is_numeric($value) && (float)$value > 0) {
            $number = (float)$value;
            if ($number > 10_000_000_000) $number /= 1000;
            return gmdate('c', (int)$number);
        }
        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? gmdate('c', $timestamp) : null;
    }
}
