<?php
declare(strict_types=1);
namespace Kiosky\Core;

final class SlideEventSelection
{
    public static function select(array $events,array $selection=[],?array $linked=null,?int $now=null): ?array
    {
        $mode=$selection['mode'] ?? 'linked';
        if(!in_array($mode,['current','today','next'],true))return $linked;
        $now??=time();$zone=new \DateTimeZone('Europe/Berlin');
        $day=static fn(int $time):string=>(new \DateTimeImmutable('@'.$time))->setTimezone($zone)->format('Y-m-d');
        $lower=static fn(string $value):string=>function_exists('mb_strtolower')?mb_strtolower(trim($value),'UTF-8'):strtr(strtolower(trim($value)),['Ä'=>'ä','Ö'=>'ö','Ü'=>'ü']);$room=is_string($selection['room'] ?? null)?$lower($selection['room']):'';
        $candidates=array_values(array_filter($events,static fn(array $event):bool=>in_array($event['status'] ?? '',['ready','published','sold_out'],true)&&empty($event['deletedAt'])&&empty($event['archivedAt'])&&preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:\d{2})$/',(string)($event['eventStart'] ?? ''))===1&&strtotime((string)($event['eventStart'] ?? ''))!==false&&($room===''||$lower((string)($event['room'] ?? ''))===$room)));
        usort($candidates,static fn(array $a,array $b):int=>strtotime($a['eventStart'])<=>strtotime($b['eventStart']) ?: strcmp($a['id'],$b['id']));
        $running=null;
        foreach($candidates as $event)if(strtotime($event['eventStart'])<=$now&&(!empty($event['eventEnd'])?strtotime($event['eventEnd'])>$now:$day(strtotime($event['eventStart']))===$day($now)))$running=$event;
        if($mode==='current')return $running;
        if($mode==='next'){foreach($candidates as $event)if(strtotime($event['eventStart'])>=$now)return $event;return null;}
        if($running)return $running;
        $last=null;foreach($candidates as $event)if($day(strtotime($event['eventStart']))===$day($now)){if(strtotime($event['eventStart'])>=$now)return $event;$last=$event;}
        return $last;
    }
}
