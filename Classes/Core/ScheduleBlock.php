<?php

declare(strict_types=1);
namespace Kiosky\Core;

final class ScheduleBlock
{
    private static function signature(array $row): array
    {
        foreach($row as $key=>$field)if(in_array($key,['targetType','targetId','contentType','contentId','channelId','slideId','startsAt','endsAt','recurrence','priority','status','recurrenceStartsAt','recurrenceEndsAt','description','updatedAt'],true)&&$field!==null&&!is_string($field))throw new \RuntimeException('Ungültige Zeitplan-Daten.',422);
        foreach(['recurrenceInterval','stackOrder'] as $key)if(isset($row[$key])&&!is_numeric($row[$key]))throw new \RuntimeException('Ungültige Zeitplan-Daten.',422);
        if(isset($row['recurrenceWeekdays'])&&(!is_array($row['recurrenceWeekdays'])||array_filter($row['recurrenceWeekdays'],static fn($day):bool=>!is_int($day)&&!is_float($day))))throw new \RuntimeException('Ungültige Zeitplan-Daten.',422);
        $type=(string)($row['contentType']??'channel');
        return ['targetType'=>(string)($row['targetType']??''),'targetId'=>(string)($row['targetId']??''),'contentType'=>$type,'contentId'=>(string)($row['contentId']??$row[$type==='slide'?'slideId':'channelId']??''),
            'startsAt'=>(string)($row['startsAt']??''),'endsAt'=>(string)($row['endsAt']??''),'recurrence'=>(string)($row['recurrence']??'once'),'priority'=>(string)($row['priority']??'normal'),'status'=>(string)($row['status']??'draft'),
            'recurrenceStartsAt'=>(string)($row['recurrenceStartsAt']??''),'recurrenceEndsAt'=>(string)($row['recurrenceEndsAt']??''),'recurrenceInterval'=>(float)($row['recurrenceInterval']??1),'recurrenceWeekdays'=>array_map('floatval',$row['recurrenceWeekdays']??[]),
            'stackOrder'=>isset($row['stackOrder'])?(float)$row['stackOrder']:null,'description'=>(string)($row['description']??''),'active'=>($row['active']??true)!==false,'updatedAt'=>(string)($row['updatedAt']??'')];
    }
    private static function blockKey(array $row): array
    {
        return array_intersect_key(self::signature($row),array_flip(['contentType','contentId','startsAt','endsAt','recurrence','priority','status']));
    }
    public static function plan(array $body, array $current, array $available): array
    {
        $expected=$body['entries']??null; $targets=$body['targets']??null;
        if (!is_array($expected)||!array_is_list($expected)||!count($expected)||count($expected)>1000||!is_array($targets)||!array_is_list($targets)||!count($targets)||count($targets)>1000) throw new \RuntimeException('Bitte einen Zeitplaneintrag und 1 bis 1000 Ziele auswählen.',422);
        $ids=[];$entries=[];$byId=array_column($current,null,'id');
        foreach($expected as $item) {
            $id=is_array($item)?($item['id']??null):null;
            if(!is_string($id)||$id===''||strlen($id)>200||isset($ids[$id]))throw new \RuntimeException('Ungültige Zeitplaneinträge.',422);
            $ids[$id]=true;$latest=$byId[$id]??null;
            if(!$latest||self::signature($latest)!==self::signature($item))throw new \RuntimeException('Dieser Zeitplaneintrag wurde inzwischen geändert oder entfernt. Bitte den Zeitplan neu laden und den Eintrag erneut öffnen.',409);
            $entries[]=$latest;
        }
        $base=$entries[0];$baseSignature=self::signature($base);
        if(!in_array($baseSignature['contentType'],['channel','slide'],true)||$baseSignature['contentId']==='')throw new \RuntimeException('Der Zeitplaneintrag hat keinen gültigen Inhalt.',422);
        foreach($entries as $item)if(self::blockKey($item)!==self::blockKey($base))throw new \RuntimeException('Die Zuweisungen gehören nicht zum selben Zeitplaneintrag.',422);
        $changes=$body['changes']??[];
        if(!is_array($changes)||!is_string($changes['startsAt']??null)||!is_string($changes['endsAt']??null)||strtotime($changes['startsAt'])===false||strtotime($changes['endsAt'])===false||strtotime($changes['endsAt'])<=strtotime($changes['startsAt'])||!in_array($changes['priority']??null,['normal','high','takeover'],true)||!in_array($changes['recurrence']??null,['once','daily','weekly','interval'],true))throw new \RuntimeException('Bitte einen gültigen Zeitraum, eine Priorität und eine Wiederholung angeben.',422);
        $keys=[];$used=[];$saves=[];
        foreach($targets as $target) {
            $type=is_array($target)?($target['targetType']??''):'';$id=is_array($target)?($target['targetId']??null):null;
            if(!in_array($type,['display','group','matrix'],true)||!is_string($id)||$id===''||!in_array($id,$available[$type]??[],true))throw new \RuntimeException('Ein ausgewähltes Ziel ist nicht mehr verfügbar.',422);
            $key=$type.':'.$id;if(isset($keys[$key]))throw new \RuntimeException('Ein Ziel darf nur einmal ausgewählt werden.',422);$keys[$key]=true;
            $previous=null;foreach($entries as $item)if(($item['targetType']??'')===$type&&($item['targetId']??'')===$id){$previous=$item;break;}
            if($previous)$used[$previous['id']]=true;
            $n=self::signature($previous??$base);
            $input=array_replace($previous??$base,array_intersect_key($changes,array_flip(['startsAt','endsAt','priority','recurrence'])),['targetType'=>$type,'targetId'=>$id,'contentType'=>$n['contentType'],'contentId'=>$n['contentId'],'channelId'=>$n['contentType']==='channel'?$n['contentId']:null,'slideId'=>$n['contentType']==='slide'?$n['contentId']:null,'status'=>'published','active'=>true]);
            if(!$previous)unset($input['id'],$input['createdAt'],$input['updatedAt']);
            $saves[]=['id'=>$previous['id']??null,'input'=>$input];
        }
        return ['saves'=>$saves,'removeIds'=>array_values(array_filter(array_map('strval',array_keys($ids)),static fn(string $id):bool=>!isset($used[$id])))];
    }
}
