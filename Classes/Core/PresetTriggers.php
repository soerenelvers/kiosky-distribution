<?php
declare(strict_types=1);
namespace Kiosky\Core;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;
require_once __DIR__.'/ExternalSchedule.php';

/** Pure state transitions: preset effects and occurrence receipts commit together. */
final class PresetTriggers
{
    private static function timestamp(string $value): float { return (float)(new DateTimeImmutable($value))->format('U.u'); }
    public static function stamp(float $at): string {
        $d=DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$at));
        if(!$d)throw new RuntimeException('Ungültiger Zeitpunkt.',422);
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
    public static function timing(array $row): array {
        return ['startsAt'=>$row['startsAt'],'recurrence'=>$row['recurrence'],'recurrenceTimezone'=>$row['timezone'],'recurrenceInterval'=>$row['interval'],'recurrenceWeekdays'=>$row['weekdays'],'recurrenceEndsAt'=>$row['until'] ?? null];
    }
    private static function input(array $body): array {
        foreach(['name'=>200,'presetId'=>100,'startsAt'=>100] as $key=>$limit)if(!is_string($body[$key] ?? null)||!trim($body[$key])||strlen($body[$key])>$limit)throw new RuntimeException('Name, Preset oder Auslösezeitpunkt fehlt oder ist ungültig.',422);
        if(array_key_exists('enabled',$body)&&!is_bool($body['enabled']))throw new RuntimeException('enabled muss ein Boolean sein.',422);
        try{$start=self::timestamp($body['startsAt']);}catch(Throwable){throw new RuntimeException('Ungültiger Auslösezeitpunkt.',422);}
        $schedule=ExternalSchedule::input(['target'=>['type'=>'display','id'=>'timer'],'content'=>['type'=>'slide','id'=>'timer'],'timing'=>['mode'=>'datetime','startsAt'=>$body['startsAt'],'endsAt'=>self::stamp($start+1),'timezone'=>$body['timezone'] ?? 'Europe/Berlin','recurrence'=>$body['recurrence'] ?? 'once','interval'=>$body['interval'] ?? 1,'weekdays'=>$body['weekdays'] ?? [],'until'=>$body['until'] ?? null]]);
        return ['name'=>trim($body['name']),'presetId'=>trim($body['presetId']),'enabled'=>($body['enabled'] ?? true)!==false,'startsAt'=>$schedule['startsAt'],'timezone'=>$schedule['recurrenceTimezone'],'recurrence'=>$schedule['recurrence'],'interval'=>$schedule['recurrenceInterval'],'weekdays'=>$schedule['recurrenceWeekdays'],'until'=>$schedule['recurrenceEndsAt'] ?? null];
    }
    public static function listing(array $state): array {
        $presets=array_column($state['presets'] ?? [],null,'id');$rows=array_values(array_filter($state['presetTriggers'] ?? [],static fn(array $r):bool=>empty($r['deletedAt'])));
        foreach($rows as &$r){$p=$presets[$r['presetId']] ?? []; $r['presetName']=$p['name'] ?? 'Preset nicht mehr vorhanden';$r['presetStatus']=$p['status'] ?? 'missing';}unset($r);
        usort($rows,static fn(array $a,array $b):int=>strcmp($a['nextRunAt'] ?? '9999',$b['nextRunAt'] ?? '9999')?:strcmp($a['name'],$b['name']));return $rows;
    }
    public static function runs(array $state): array {
        $triggers=array_column($state['presetTriggers'] ?? [],null,'id');$presets=array_column($state['presets'] ?? [],null,'id');$rows=array_reverse(array_slice($state['presetTriggerRuns'] ?? [],-100));
        foreach($rows as &$r){$r['triggerName']=$triggers[$r['triggerId']]['name'] ?? ''; $r['timezone']=$triggers[$r['triggerId']]['timezone'] ?? 'Europe/Berlin'; $r['presetName']=$presets[$r['presetId']]['name'] ?? '';}unset($r);return $rows;
    }
    public static function save(array &$state,array $body,?string $id=null,?float $at=null): array {
        $at??=microtime(true);$input=self::input($body);$rows=$state['presetTriggers'] ?? [];$index=$id===null?false:array_search($id,array_column($rows,'id'),true);$current=$index===false?null:$rows[$index];
        if($id!==null&&(!$current||!empty($current['deletedAt'])))throw new RuntimeException('Preset-Termin wurde nicht gefunden.',404);
        $preset=array_column($state['presets'] ?? [],null,'id')[$input['presetId']] ?? null;
        if((!$preset||($preset['status'] ?? '')!=='active')&&($input['enabled']||!$current||$current['presetId']!==$input['presetId']))throw new RuntimeException('Bitte ein aktives Preset auswählen.',422);
        if(!$current&&count(array_filter($rows,static fn(array $r):bool=>empty($r['deletedAt'])))>=500)throw new RuntimeException('Höchstens 500 Preset-Termine sind möglich.',422);
        $rearm=!$current||self::timing($current)!==self::timing($input)||$current['presetId']!==$input['presetId']||$current['enabled']!==$input['enabled'];
        if($rearm&&$input['enabled']&&$input['recurrence']==='once'&&self::timestamp($input['startsAt'])<=$at)throw new RuntimeException('Bitte einen zukünftigen Auslösezeitpunkt wählen.',422);
        $row=array_replace($current ?? ['id'=>$id ?? bin2hex(random_bytes(16)),'createdAt'=>self::stamp($at)],$input,['updatedAt'=>self::stamp($at)]);
        if($rearm){$next=$input['enabled']?ExternalSchedule::nextStart(self::timing($input),$at):null;if($input['enabled']&&$next===null)throw new RuntimeException('In diesem Zeitraum gibt es keinen zukünftigen Preset-Termin.',422);$row['nextRunAt']=$next===null?null:self::stamp($next);}
        if($current)$state['presetTriggers'][$index]=$row;else $state['presetTriggers'][]=$row;
        foreach(self::listing($state) as $item)if($item['id']===$row['id'])return $item;
        throw new RuntimeException('Preset-Termin konnte nicht gespeichert werden.',500);
    }
    public static function delete(array &$state,string $id): void {
        foreach($state['presetTriggers'] ?? [] as $index=>$row)if($row['id']===$id&&empty($row['deletedAt'])){$state['presetTriggers'][$index]=array_replace($row,['enabled'=>false,'nextRunAt'=>null,'deletedAt'=>self::stamp(microtime(true))]);self::prune($state);return;}
        throw new RuntimeException('Preset-Termin wurde nicht gefunden.',404);
    }
    private static function prune(array &$state): void {
        $referenced=array_flip(array_column($state['presetTriggerRuns'] ?? [],'triggerId'));
        $state['presetTriggers']=array_values(array_filter($state['presetTriggers'] ?? [],static fn(array $row):bool=>empty($row['deletedAt'])||isset($referenced[$row['id']])));
    }
    private static function receipt(array $value): array {
        $result=$value['result'] ?? $value;
        return ['warningId'=>$value['warning']['id'] ?? null,'assignments'=>count($result['assignments'] ?? []),'channelUpdates'=>count($result['channelUpdates'] ?? []),'schedules'=>count($result['schedules'] ?? [])];
    }
    public static function runDue(array &$state,callable $execute,?float $at=null): int {
        $at??=microtime(true);$due=array_values(array_filter($state['presetTriggers'] ?? [],static fn(array $r):bool=>!empty($r['enabled'])&&empty($r['deletedAt'])&&!empty($r['nextRunAt'])&&self::timestamp($r['nextRunAt'])<=$at));
        usort($due,static fn(array $a,array $b):int=>strcmp($a['nextRunAt'],$b['nextRunAt'])?:strcmp($a['id'],$b['id']));$count=0;
        foreach(array_slice($due,0,50) as $row){
            $dueAt=self::timestamp($row['nextRunAt']);$recent=ExternalSchedule::nextStart(self::timing($row),max($dueAt,$at-300));
            $occurrence=self::stamp($recent!==null&&$recent<=$at?$recent:$dueAt);$key=hash('sha256',$row['id'].'|'.$occurrence);$existing=array_column($state['presetTriggerRuns'] ?? [],null,'id');$status='skipped';$error=null;$result=[];
            if(!isset($existing[$key])){
                if($at-self::timestamp($occurrence)>300)$error='Termin um mehr als fünf Minuten verpasst; nicht nachträglich ausgelöst.';
                else{
                    $before=$state;
                    try{$result=$execute($state,$row['presetId']);$status='success';}
                    catch(Throwable $failure){$state=$before;$status='failed';$error=substr($failure->getMessage(),0,1000);}
                }
                $state['presetTriggerRuns'][]=['id'=>$key,'triggerId'=>$row['id'],'presetId'=>$row['presetId'],'scheduledAt'=>$occurrence,'executedAt'=>self::stamp($at),'status'=>$status,'error'=>$error,'result'=>self::receipt($result)];$count++;
            }
            $index=array_search($row['id'],array_column($state['presetTriggers'],'id'),true);$next=ExternalSchedule::nextStart(self::timing($row),$at+.001);
            $state['presetTriggers'][$index]=array_replace($row,['nextRunAt'=>$next===null?null:self::stamp($next),'lastRunAt'=>self::stamp($at),'lastStatus'=>$status,'lastError'=>$error]);
        }
        if($count){$state['presetTriggerRuns']=array_slice($state['presetTriggerRuns'],-2000);self::prune($state);}
        return $count;
    }
}
