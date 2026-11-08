<?php
declare(strict_types=1);
namespace Kiosky\Core;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
final class EventReuse
{
    private const LENGTHS=['title'=>300,'subtitle'=>300,'description'=>5000,'organizer'=>300,'eventType'=>200,'venue'=>300,'room'=>200,'publicNotes'=>5000,'internalNotes'=>5000,'imageUrl'=>2000,'ticketUrl'=>2000,'remainingTickets'=>200];
    private const TIMES=['setupStart','admissionStart','eventStart','breakStart','breakEnd','eventEnd','boxOfficeOpenAt'];
    private static function length(string $value):int{return preg_match_all('/./us',$value)+preg_match_all('/[\x{10000}-\x{10FFFF}]/u',$value);}
    public static function copyTitle(string $title):string{$result='';foreach(preg_split('//u',$title.' (Kopie)',-1,PREG_SPLIT_NO_EMPTY) as $char){if(self::length($result.$char)>300)break;$result.=$char;}return $result;}
    private static function text(mixed $value,int $max,bool $required=false):?string
    {
        if($value===null||$value===''){if($required)throw new RuntimeException('Ein Name oder Titel fehlt.',422);return null;}
        if(!is_string($value)||self::length($value)>$max)throw new RuntimeException('Eine Vorlagenangabe ist ungültig oder zu lang.',422);
        $value=trim($value);if($required&&$value==='')throw new RuntimeException('Ein Name oder Titel fehlt.',422);return $value!==''?$value:null;
    }
    public static function date(mixed $value):?string
    {
        if($value===null||$value==='')return null;$date=(string)$value;if(preg_match('/^\d{8}$/',$date))$date=substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2);
        if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))throw new RuntimeException('Das Veranstaltungsdatum ist ungültig.',422);return $date;
    }
    private static function timestamp(mixed $value,bool $required=false):?string
    {
        if($value===null||$value===''){if($required)throw new RuntimeException('Ein Ablaufzeitpunkt fehlt.',422);return null;}
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:\d{2})$/',$value)||strtotime($value)===false)throw new RuntimeException('Ein Veranstaltungszeitpunkt ist ungültig.',422);
        self::date(substr($value,0,10));return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
    private static function local(string $value):string{return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d\TH:i:s');}
    public static function input(mixed $raw):array
    {
        if(!is_array($raw))throw new RuntimeException('Die Veranstaltungsangaben sind ungültig.',422);
        $result=['status'=>'draft','boxOfficeAvailable'=>($raw['boxOfficeAvailable'] ?? false)===true];
        foreach(self::LENGTHS as $key=>$max){$value=self::text($raw[$key] ?? null,$max,$key==='title');if($value!==null)$result[$key]=$value;}
        foreach(self::TIMES as $key){$value=self::timestamp($raw[$key] ?? null);if($value!==null)$result[$key]=$value;}
        $anchor=$result['eventStart'] ?? null;if(!$anchor)foreach(self::TIMES as $key)if(isset($result[$key])){$anchor=$result[$key];break;}$date=self::date($raw['date'] ?? null) ?? ($anchor?substr(self::local($anchor),0,10):null);if($date)$result['date']=str_replace('-','',$date);
        if(isset($result['eventStart'],$result['eventEnd'])&&$result['eventEnd']<$result['eventStart'])throw new RuntimeException('Das Veranstaltungsende liegt vor dem Beginn.',422);return $result;
    }
    public static function schedule(mixed $raw):array
    {
        if($raw===null)return [];if(!is_array($raw)||count($raw)>500||!array_is_list($raw))throw new RuntimeException('Maximal 500 Ablaufpunkte pro Vorlage sind möglich.',422);
        $entries=[];foreach($raw as $value){if(!is_array($value))throw new RuntimeException('Ein Ablaufpunkt ist ungültig.',422);$entry=['type'=>self::text($value['type'] ?? null,300,true),'startAt'=>self::timestamp($value['startAt'] ?? null,true),'isApproximate'=>($value['isApproximate'] ?? false)===true,'sortOrder'=>$value['sortOrder'] ?? 0];
            if((!is_int($entry['sortOrder'])&&(!is_float($entry['sortOrder'])||floor($entry['sortOrder'])!==$entry['sortOrder']))||abs($entry['sortOrder'])>100000)throw new RuntimeException('Die Ablaufreihenfolge ist ungültig.',422);$entry['sortOrder']=(int)$entry['sortOrder'];
            foreach(['caption','roomName','roomConfiguration'] as $key){$text=self::text($value[$key] ?? null,300);if($text!==null)$entry[$key]=$text;}
            if(isset($value['category'])&&$value['category']!==''){if(!in_array($value['category'],['access','setup','rehearsal','event','break','hospitality','teardown','internal','cancelled','other'],true))throw new RuntimeException('Die Ablaufkategorie ist ungültig.',422);$entry['category']=$value['category'];}
            $end=self::timestamp($value['endAt'] ?? null);if($end!==null){if($end<$entry['startAt'])throw new RuntimeException('Das Ablaufende liegt vor dem Beginn.',422);$entry['endAt']=$end;}$entries[]=$entry;
        }return $entries;
    }
    private static function localInstant(string $local):string
    {
        $desired=strtotime($local.'Z');$zone=new DateTimeZone('Europe/Berlin');$candidates=[];
        foreach($zone->getTransitions($desired-172800,$desired+172800) as $transition){$candidate=$desired-$transition['offset'];if((new DateTimeImmutable('@'.$candidate))->setTimezone($zone)->format('Y-m-d\TH:i:s')===$local)$candidates[]=$candidate;}
        if(!$candidates)throw new RuntimeException('Diese lokale Uhrzeit existiert wegen der Zeitumstellung nicht.',422);return gmdate('Y-m-d\TH:i:s',min($candidates)).'.000Z';
    }
    public static function copy(array $snapshot,array $options=[]):array
    {
        $input=self::input($snapshot['input'] ?? []);$schedule=self::schedule($snapshot['schedule'] ?? []);$old=self::date($input['date'] ?? null) ?? (isset($schedule[0])?substr(self::local($schedule[0]['startAt']),0,10):null);$date=self::date($options['date'] ?? null) ?? $old;
        if(array_key_exists('title',$options))$input['title']=self::text($options['title'],300,true);if($date)$input['date']=str_replace('-','',$date);
        if($date&&$old&&$date!==$old){$shift=static function(string $value)use($date,$old):string{$wall=self::local($value);$days=(int)round((strtotime(substr($wall,0,10).'T00:00:00Z')-strtotime($old.'T00:00:00Z'))/86400);$day=gmdate('Y-m-d',strtotime($date.'T00:00:00Z')+$days*86400);return self::localInstant($day.substr($wall,10));};foreach(self::TIMES as $key)if(isset($input[$key]))$input[$key]=$shift($input[$key]);foreach($schedule as &$entry){$entry['startAt']=$shift($entry['startAt']);if(isset($entry['endAt']))$entry['endAt']=$shift($entry['endAt']);}unset($entry);}
        return ['input'=>self::input($input),'schedule'=>self::schedule($schedule)];
    }
    public static function templates(array $state):array
    {
        $records=array_key_exists('eventTemplates',$state)?$state['eventTemplates']:[];if(!is_array($records)||!array_is_list($records)||count($records)>200)throw new RuntimeException('Veranstaltungsvorlagen konnten nicht gelesen werden.',500);
        foreach($records as $record)if(!is_array($record)||!is_string($record['id'] ?? null)||!is_string($record['name'] ?? null)||!is_array($record['input'] ?? null)||!is_array($record['schedule'] ?? null))throw new RuntimeException('Veranstaltungsvorlagen konnten nicht gelesen werden.',500);return $records;
    }
    public static function get(array $state,string $id):array{foreach(self::templates($state) as $record)if($record['id']===$id)return $record;throw new RuntimeException('Veranstaltungsvorlage wurde nicht gefunden.',404);}
    public static function save(array &$state,mixed $name,array $snapshot,?string $id=null):array
    {
        $records=self::templates($state);$index=null;foreach($records as $i=>$record)if($record['id']===$id)$index=$i;if($id!==null&&$index===null)throw new RuntimeException('Veranstaltungsvorlage wurde nicht gefunden.',404);if($id===null&&count($records)>=200)throw new RuntimeException('Maximal 200 Veranstaltungsvorlagen sind möglich.',422);
        $now=gmdate('c');$record=['id'=>$id ?? bin2hex(random_bytes(16)),'name'=>self::text($name,160,true),'input'=>self::input($snapshot['input'] ?? []),'schedule'=>self::schedule($snapshot['schedule'] ?? []),'createdAt'=>$index!==null?$records[$index]['createdAt']:$now,'updatedAt'=>$now];if($index===null)$records[]=$record;else $records[$index]=$record;if(strlen(json_encode($records,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))>5000000)throw new RuntimeException('Veranstaltungsvorlagen sind zu groß. Bitte Umfang oder Anzahl reduzieren.',422);$state['eventTemplates']=$records;return $record;
    }
    public static function delete(array &$state,string $id):void{self::get($state,$id);$state['eventTemplates']=array_values(array_filter(self::templates($state),static fn(array $record):bool=>$record['id']!==$id));}
}
