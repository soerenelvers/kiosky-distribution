<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class WarningPresentation
{
 public static function settings(mixed $value,bool $strict=false):array{
  if($strict&&$value!==null&&!is_array($value))throw new \RuntimeException('Ungültige DWD-Anzeigeregel.',422);
  $input=is_array($value)?$value:[];$mode=$input['mode'] ?? 'continuous';$duration=$input['durationSeconds'] ?? 30;$interval=$input['intervalMinutes'] ?? 10;
  if(!in_array($mode,['continuous','once','interval'],true)||!is_int($duration)||$duration<1||$duration>86400||!is_int($interval)||$interval<1||$interval>10080||$mode==='interval'&&$duration>$interval*60){if($strict)throw new \RuntimeException('Bitte eine Anzeigedauer von 00:01 bis 1440:00 und ein passendes Wiederholungsintervall wählen.',422);return ['mode'=>'continuous','durationSeconds'=>30,'intervalMinutes'=>10];}
  return ['mode'=>$mode,'durationSeconds'=>$duration,'intervalMinutes'=>$interval];
 }
 public static function select(array $candidates,array $ledger,int $nowMs,bool $preview=false):array{
  $changed=false;$nextChangeAtMs=null;$boundary=static function(int $at)use(&$nextChangeAtMs,$nowMs):void{if($at>$nowMs&&($nextChangeAtMs===null||$at<$nextChangeAtMs))$nextChangeAtMs=$at;};
  foreach($candidates as $warning){
   $rule=self::settings(($warning['source'] ?? '')==='dwd'?($warning['dwdPresentation'] ?? null):null);
   if($rule['mode']==='continuous')return ['warning'=>$warning,'ledger'=>$ledger,'changed'=>$changed,'nextChangeAtMs'=>$nextChangeAtMs];
   $id=(string)$warning['id'];$key=json_encode([$rule,(string)($warning['dwdPresentationRevision'] ?? 'legacy')],JSON_UNESCAPED_SLASHES);$stored=$ledger[$id] ?? null;$start=is_array($stored)&&($stored['key'] ?? '')===$key&&is_int($stored['startedAtMs'] ?? null)?$stored['startedAtMs']:$nowMs;
   if(!is_array($stored)||($stored['key'] ?? '')!==$key||!is_int($stored['startedAtMs'] ?? null)){if(!$preview){$ledger[$id]=['key'=>$key,'startedAtMs'=>$start];$changed=true;}}
   $duration=$rule['durationSeconds']*1000;$age=max(0,$nowMs-$start);$period=$rule['intervalMinutes']*60000;$cycle=$rule['mode']==='interval'?intdiv($age,$period):0;$phase=$rule['mode']==='interval'?$age%$period:$age;
   if($phase<$duration){$expires=strtotime((string)($warning['endsAt'] ?? ''));$until=min($start+$cycle*$period+$duration,$expires!==false?$expires*1000:PHP_INT_MAX);$boundary($until);$warning['presentationEndsAt']=gmdate('Y-m-d\TH:i:s',intdiv($until,1000)).sprintf('.%03dZ',$until%1000);return ['warning'=>$warning,'ledger'=>$ledger,'changed'=>$changed,'nextChangeAtMs'=>$nextChangeAtMs];}
   if($rule['mode']==='interval')$boundary($start+($cycle+1)*$period);
  }
  return ['warning'=>null,'ledger'=>$ledger,'changed'=>$changed,'nextChangeAtMs'=>$nextChangeAtMs];
 }
}
