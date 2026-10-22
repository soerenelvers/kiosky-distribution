<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;

final class DwdService
{
    public const WARNCELL_INFO_URL='https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_warncellids_csv.html';
    private const WARNCELL_URL='https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_warncellids_csv.csv?__blob=publicationFile&v=4';
    private const URL = 'https://www.dwd.de/DWD/warnungen/warnapp/json/warnings.json';

    public function __construct(private readonly HttpClient $http)
    {
    }

    public function warncells(): array
    {
        $response=$this->http->request('GET',self::WARNCELL_URL,['Accept'=>'text/csv,text/plain;q=0.9'],null,12);
        if($response->status!==200)throw new RuntimeException('DWD-Warngebietsliste antwortet mit HTTP '.$response->status.'.',502);
        $lines=preg_split('/\r?\n/',ltrim($response->body,"\xEF\xBB\xBF"));
        $fields=static fn(string $line):array=>array_map('trim',str_getcsv($line,';','"',''));
        $headers=array_map('strtoupper',$fields(array_shift($lines) ?? ''));
        foreach(['WARNCELLID','NAME','BL'] as $key)if(!in_array($key,$headers,true))throw new RuntimeException('Die DWD-Warngebietsliste hat ein unerwartetes Datenformat.',502);
        $rows=[];foreach($lines as $line){if(trim($line)==='')continue;$values=$fields($line);$row=[];foreach($headers as $i=>$header)$row[$header]=$values[$i] ?? '';
            $id=preg_replace('/\D/','',$row['WARNCELLID']);if($id===''||$row['NAME']==='')continue;
            $rows[]=['id'=>$id,'name'=>$row['NAME'],'shortName'=>($row['KURZNAME'] ?? '') ?: $row['NAME'],'abbreviation'=>$row['CCC'] ?? '','state'=>$row['BL']];
        }
        if(!$rows)throw new RuntimeException('Die DWD-Warngebietsliste enthält keine Einträge.',502);
        return ['data'=>$rows,'loadedAt'=>gmdate('c')];
    }

    public static function searchWarncells(array $cells,string $query): array
    {
        $normalize=static function(string $text):string{$text=strtr($text,['ß'=>'ss','ẞ'=>'ss']);$text=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text) ?: $text;return strtolower($text);};
        $query=$normalize(trim($query));if(strlen($query)<2)return [];
        $cells=array_values(array_filter($cells,static fn(array $cell):bool=>str_contains($normalize(implode(' ',[$cell['id'],$cell['name'],$cell['shortName'],$cell['abbreviation'] ?? '',$cell['state']])),$query)));
        $score=static function(array $cell)use($normalize,$query):int{if($cell['id']===$query)return 0;if(str_starts_with($cell['id'],$query))return 1;foreach(['name','shortName'] as $key)if($normalize($cell[$key])===$query)return 2;foreach(['name','shortName'] as $key)if(str_starts_with($normalize($cell[$key]),$query))return 3;foreach(['name','shortName'] as $key)foreach(preg_split('/\W+/',$normalize($cell[$key])) as $word)if(str_starts_with($word,$query))return 4;return in_array($query,[$normalize($cell['abbreviation'] ?? ''),$normalize($cell['state'])],true)?5:6;};
        usort($cells,static fn(array $a,array $b):int=>($score($a)<=>$score($b)) ?: strcmp($a['name'],$b['name']));return array_slice($cells,0,30);
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
