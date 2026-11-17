<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;
require_once __DIR__.'/WarningPresentation.php';

final class DwdService
{
    public const WARNCELL_INFO_URL='https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_warncellids_csv.html';
    private const WARNCELL_URL='https://www.dwd.de/DE/leistungen/opendata/help/warnungen/cap_warncellids_csv.csv?__blob=publicationFile&v=4';
    private const MUNICIPAL_URL='https://maps.dwd.de/geoserver/dwd/ows';
    private const LIMIT=8388608;
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
        $code=trim($warningAreaCode);
        if(!preg_match('/^\d{9}(?:\d{3})?$/',$code))throw new RuntimeException('Bitte eine gültige 9-stellige DWD-WarnCellID hinterlegen.',422);
        $code=substr($code,0,9);$municipal=str_starts_with($code,'8');
        $url=$municipal?self::MUNICIPAL_URL.'?'.http_build_query(['service'=>'WFS','version'=>'2.0.0','request'=>'GetFeature','typeNames'=>'dwd:Warnungen_Gemeinden','outputFormat'=>'application/json','CQL_FILTER'=>'WARNCELLID='.$code,'count'=>'100','propertyName'=>'WARNCELLID,NAME,IDENTIFIER,SENT,STATUS,MSGTYPE,SCOPE,LANGUAGE,EVENT,SEVERITY,ONSET,EFFECTIVE,EXPIRES,HEADLINE,DESCRIPTION,INSTRUCTION'],'','&',PHP_QUERY_RFC3986):self::URL;
        $response=$this->http->request('GET',$url,['Accept'=>'application/json,text/plain;q=0.9','User-Agent'=>'Kiosky DWD warning integration'],null,12);
        if ($response->status < 200 || $response->status >= 300) {
            throw new RuntimeException('DWD-Warndienst antwortet mit HTTP ' . $response->status . '.', 502);
        }
        if(strlen($response->body)>self::LIMIT)throw new RuntimeException('Die Antwort des DWD-Warndienstes ist zu groß.',502);
        if($municipal)return $this->municipalWarnings($response->body,$code);
        if (!preg_match('/^warnWetter\.loadWarnings\(([\s\S]+)\);\s*$/', trim($response->body), $match)) {
            throw new RuntimeException('Der DWD-Warndienst hat ein unerwartetes Datenformat geliefert.', 502);
        }
        try {
            $payload = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Der DWD-Warndienst hat ungültige Warndaten geliefert.', 502);
        }
        if(!is_array($payload)||!isset($payload['warnings'])||!is_object(json_decode($match[1])->warnings ?? null))throw new RuntimeException('Der DWD-Warndienst hat kein gültiges Warnverzeichnis geliefert.',502);
        $all=$payload['warnings'];
        $candidates = array_unique(array_filter([$code, strlen($code) > 9 ? substr($code, 0, 9) : $code]));
        $updatedAt = $this->timestamp($payload['time'] ?? null);
        $result = [];
        foreach ($all as $cellId => $entries) {
            if((string)$cellId!==$code)continue;
            if (!is_array($entries))throw new RuntimeException('Die DWD-Warnliste für dieses Gebiet ist ungültig.',502);
            foreach ($entries as $entry) {
                if (!is_array($entry))throw new RuntimeException('Eine DWD-Warnung ist ungültig.',502);
                $event = (string)($entry['event'] ?? 'Wetterwarnung');
                $startsAt = $this->timestamp($entry['start'] ?? null);$endsAt=$this->timestamp($entry['end'] ?? null);
                if(!$startsAt||!$endsAt||strtotime($endsAt)<=strtotime($startsAt))throw new RuntimeException('Eine DWD-Warnung enthält ungültige Gültigkeitszeiten.',502);
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
                    'endsAt' => $endsAt,
                    'updatedAt' => $updatedAt,
                ], static fn(mixed $value): bool => $value !== null && $value !== '');
            }
        }
        usort($result, static fn(array $left, array $right): int => $right['severity'] <=> $left['severity']);
        return $result;
    }

    public static function configurationKey(array $value,string $locationId):string
    {
        return json_encode(['enabled'=>($value['enabled'] ?? true)!==false,'confirmationRequired'=>($value['confirmationRequired'] ?? true)!==false,'minSeverity'=>(int)($value['minSeverity'] ?? 2),'eventTypes'=>array_values($value['eventTypes'] ?? []),'targets'=>array_values($value['targets'] ?? ['location:'.$locationId]),'presentation'=>WarningPresentation::settings($value['presentation'] ?? null),'presentationRevision'=>(string)($value['presentationRevision'] ?? 'legacy')],JSON_THROW_ON_ERROR);
    }

    public static function selectWarnings(array $warnings,array $configuration,?int $at=null):array
    {
        $at ??= time();$lower=static fn($value)=>strtolower(strtr(trim((string)$value),['Ä'=>'ä','Ö'=>'ö','Ü'=>'ü','ẞ'=>'ß']));$types=array_values(array_filter(array_map($lower,is_array($configuration['eventTypes'] ?? null)?$configuration['eventTypes']:[])));
        return array_values(array_filter($warnings,static function(array $warning)use($configuration,$types,$at,$lower):bool{
            if(!empty($warning['endsAt'])&&strtotime($warning['endsAt'])<=$at)return false;
            if((int)($warning['severity'] ?? 0)<(int)($configuration['minSeverity'] ?? 2))return false;
            if(!$types)return true;$event=$lower($warning['warningType'] ?? '');foreach($types as $type)if(str_contains($event,$type))return true;return false;
        }));
    }

    private function municipalWarnings(string $source,string $code):array
    {
        try{$root=json_decode($source,false,512,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new RuntimeException('Der DWD-Warndienst hat ungültige Warndaten geliefert.',502);}
        if(!is_object($root)||($root->type ?? '')!=='FeatureCollection'||!is_array($root->features ?? null)||count($root->features)>=100||(is_numeric($root->numberMatched ?? null)&&(int)$root->numberMatched>count($root->features)))throw new RuntimeException('Der DWD-Gemeindedienst hat unvollständige oder ungültige Warndaten geliefert.',502);
        foreach([$root->numberMatched ?? null,$root->numberReturned ?? null]as$count)if($count!==null&&$count!=='unknown'&&(!is_numeric($count)||(float)$count!==(float)count($root->features)))throw new RuntimeException('Der DWD-Gemeindedienst hat unvollständige Warndaten geliefert.',502);
        $result=[];
        foreach($root->features as $feature){
            if(!is_object($feature)||!is_object($feature->properties ?? null))throw new RuntimeException('Eine DWD-Gemeindewarnung ist ungültig.',502);
            $row=(array)$feature->properties;
            if((string)($row['WARNCELLID'] ?? '')!==$code)throw new RuntimeException('Der DWD-Gemeindedienst hat ein anderes Warngebiet geliefert.',502);
            if(!in_array($row['STATUS'] ?? '',['Actual','Exercise','System','Test','Draft'],true)||!in_array($row['MSGTYPE'] ?? '',['Alert','Update','Cancel','Ack','Error'],true)||!in_array($row['SCOPE'] ?? '',['Public','Restricted','Private'],true)||!is_string($row['LANGUAGE'] ?? null)||!preg_match('/^[a-z]{2}(?:-[a-z0-9]+)*$/i',$row['LANGUAGE']))throw new RuntimeException('Eine DWD-Gemeindewarnung enthält ungültige Pflichtangaben.',502);
            if(($row['STATUS'] ?? '')!=='Actual'||!in_array($row['MSGTYPE'] ?? '',['Alert','Update'],true)||($row['SCOPE'] ?? '')!=='Public'||!str_starts_with(strtolower((string)($row['LANGUAGE'] ?? '')),'de'))continue;
            $start=$this->timestamp($row['ONSET'] ?? $row['EFFECTIVE'] ?? null);$end=$this->timestamp($row['EXPIRES'] ?? null);$sent=$this->timestamp($row['SENT'] ?? null);
            $id=is_string($row['IDENTIFIER'] ?? null)?trim($row['IDENTIFIER']):'';$title=is_string($row['HEADLINE'] ?? null)?trim($row['HEADLINE']):'';$severity=['Minor'=>1,'Moderate'=>2,'Severe'=>3,'Extreme'=>4][$row['SEVERITY'] ?? ''] ?? null;
            if(!$id||!$title||!$start||!$end||!$sent||!$severity||strtotime($end)<=strtotime($start))throw new RuntimeException('Eine DWD-Gemeindewarnung enthält ungültige Pflichtangaben.',502);
            $externalId='dwd-'.substr(hash('sha256',$code.'|'.$id),0,24);
            $result[$externalId]=array_filter(['externalId'=>$externalId,'source'=>'dwd','warningType'=>(string)($row['EVENT'] ?? 'Wetterwarnung'),'severity'=>$severity,'title'=>$title,'description'=>is_string($row['DESCRIPTION'] ?? null)?$row['DESCRIPTION']:null,'instructions'=>is_string($row['INSTRUCTION'] ?? null)?$row['INSTRUCTION']:null,'areaName'=>is_string($row['NAME'] ?? null)?$row['NAME']:null,'startsAt'=>gmdate('Y-m-d\TH:i:s.000\Z',strtotime($start)),'endsAt'=>gmdate('Y-m-d\TH:i:s.000\Z',strtotime($end)),'updatedAt'=>gmdate('Y-m-d\TH:i:s.000\Z',strtotime($sent))],static fn($v)=>$v!==null);
        }
        $result=array_values($result);usort($result,static fn($a,$b)=>$b['severity']<=>$a['severity']);return $result;
    }

    private function timestamp(mixed $value): ?string
    {
        if($value===null||$value==='')return null;
        if (is_numeric($value) && (float)$value > 0) {
            $number = (float)$value;
            if ($number > 10_000_000_000) $number /= 1000;
            return gmdate('c', (int)$number);
        }
        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? gmdate('c', $timestamp) : null;
    }
}
