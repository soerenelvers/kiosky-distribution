<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class EventLifecycle
{
    public static function status(mixed $value):string
    {
        $status=strtolower((string)($value ?: 'draft'));
        $status=['review'=>'draft','ready'=>'published','sold_out'=>'published','ended'=>'archived','deleted'=>'removed'][$status] ?? $status;
        if(!in_array($status,['draft','published','cancelled','archived','removed'],true))throw new \RuntimeException('Der Veranstaltungsstatus ist ungültig.',422);
        return $status;
    }
    public static function ended(array $event,?int $now=null):bool
    {
        $now??=time();$zone=new \DateTimeZone('Europe/Berlin');$end=trim((string)($event['eventEnd'] ?? ''));
        if($end!=='' && preg_match('/^\d{4}-\d\d-\d\dT/',$end))try{if((new \DateTimeImmutable($end,$zone))->getTimestamp()<=$now)return true;else return false;}catch(\Throwable){}
        $date=preg_replace('/\D/','',(string)($event['date'] ?? $event['eventStart'] ?? ''));$date=substr($date,0,8);
        $day=\DateTimeImmutable::createFromFormat('!Ymd',$date,$zone);if(!$day||$day->format('Ymd')!==$date)return false;
        return $date<(new \DateTimeImmutable('@'.$now))->setTimezone($zone)->format('Ymd');
    }
    public static function effective(array $event,?int $now=null):string
    {
        $status='draft';try{$status=self::status($event['status'] ?? 'draft');}catch(\RuntimeException){}if(!empty($event['deletedAt'])||$status==='removed')return 'removed';
        return !empty($event['archivedAt'])||$status==='archived'||self::ended($event,$now)?'archived':$status;
    }
    public static function visible(array $event,?int $now=null):bool{return in_array(self::effective($event,$now),['published','cancelled'],true);}
    public static function tombstone(array $event,string $now):array
    {
        return array_replace(array_intersect_key($event,array_flip(['id','title','date','eventStart','externalNumber','sourceId','externalId','externalObjectType','createdAt'])),['status'=>'removed','deletedAt'=>$event['deletedAt'] ?? $now,'updatedAt'=>($event['status'] ?? '')==='removed'?($event['updatedAt'] ?? $now):$now,'readOnly'=>true]);
    }
    public static function synchronize(array &$state):bool
    {
        $changed=false;$now=gmdate('Y-m-d\TH:i:s\Z');$removed=[];
        foreach($state['events'] as &$event){$status=self::effective($event);$old=$event;
            if(($event['status'] ?? '')==='sold_out'&&empty($event['remainingTickets']))$event['remainingTickets']='Ausverkauft';
            if($status==='removed'){$event=self::tombstone($event,$now);$removed[$event['id']]=true;}
            else {if(($event['status'] ?? '')!==$status){$event['status']=$status;$event['updatedAt']=$now;}if($status==='archived')$event['archivedAt']??=$now;}
            if($event!==$old)$changed=true;
        }unset($event);
        if($removed){foreach(['eventSchedules','easyjobScheduleImportLogs'] as $key)if(isset($state[$key])){$rows=array_values(array_filter($state[$key],static fn(array $r):bool=>!isset($removed[$r['eventId'] ?? ''])));if($rows!==$state[$key]){$state[$key]=$rows;$changed=true;}}
            foreach($state['slides'] as &$slide)if(isset($removed[$slide['eventId'] ?? ''])){unset($slide['eventId']);$changed=true;}unset($slide);
            foreach($state['channels'] as &$channel)if(isset($removed[$channel['eventId'] ?? ''])){unset($channel['eventId']);$changed=true;}unset($channel);
            if(isset($state['trash'])){$rows=array_values(array_filter($state['trash'],static fn(array $r):bool=>($r['type'] ?? '')!=='event'||!isset($removed[$r['id'] ?? ''])));if($rows!==$state['trash']){$state['trash']=$rows;$changed=true;}}
        }
        return $changed;
    }
}
