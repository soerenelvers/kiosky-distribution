<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class WarningPresentation
{
 private static function timestamp(mixed $value):?int{
  if(!is_string($value)||trim($value)==='')return null;
  try{$milliseconds=round((float)(new \DateTimeImmutable($value))->format('U.u')*1000);return abs($milliseconds)<=8640000000000000?(int)$milliseconds:null;}catch(\Throwable){return null;}
 }
 public static function externalIds(array $input):array{
  $values=$input['externalIds']??(isset($input['externalId'])?[$input['externalId']]:null);
  if(isset($input['externalIds'],$input['externalId'])||!is_array($values)||!$values||count($values)>20||array_filter($values,static fn($value)=>!is_string($value)||$value===''||strlen($value)>1000))throw new \RuntimeException('Bitte eine bis zwanzig gültige DWD-Warnungen auswählen.',422);
  return array_values(array_unique($values));
 }
 public static function activation(array $input,array $knownTargets,string $revision):array{
  if(array_diff(array_keys($input),['externalId','externalIds','replaceGroupId','targets','presentation']))throw new \RuntimeException('Amtliche Warnungsdaten können nicht verändert werden.',422);
  if(isset($input['replaceGroupId'])&&(!is_string($input['replaceGroupId'])||!preg_match('/^[a-f0-9-]{36}$/iD',$input['replaceGroupId'])))throw new \RuntimeException('Die gemeinsame Ausspielung ist ungültig.',422);
  $values=$input['targets']??null;
  if(!is_array($values)||!$values||count($values)>500||array_filter($values,static fn($value)=>!is_string($value)||!in_array($value,$knownTargets,true)))throw new \RuntimeException('Bitte gültige Displays, Gruppen oder Standorte auswählen.',422);
  if(empty($input['presentation']))throw new \RuntimeException('Bitte eine Anzeigeregel auswählen.',422);
  return ['targets'=>array_values(array_unique($values)),'presentation'=>self::settings($input['presentation'],true),'revision'=>$revision];
 }
 public static function grouped(array $candidates):array{
  $groups=[];foreach($candidates as $warning){$id=$warning['document']['dwdActivation']['groupId']??null;if(($warning['source']??'')==='dwd'&&is_string($id))$groups[$id][]=$warning;}
  $result=[];$seen=[];foreach($candidates as $warning){$id=$warning['document']['dwdActivation']['groupId']??null;if(($warning['source']??'')!=='dwd'||!is_string($id)){$result[]=$warning;continue;}if(isset($seen[$id]))continue;$seen[$id]=true;$members=$groups[$id];usort($members,static fn($a,$b)=>(int)($b['severity']??0)<=>(int)($a['severity']??0)?:strcmp((string)($a['externalId']??$a['id']),(string)($b['externalId']??$b['id'])));$warning['id']='dwd-group:'.$id;$warning['title']=count($members).' DWD-Warnungen';$warning['warnings']=array_map(static fn($item)=>array_intersect_key($item,array_flip(['id','externalId','title','severity','description','instructions','areaName','startsAt','endsAt'])),$members);$ends=array_filter(array_column($members,'endsAt'),'is_string');if($ends)$warning['endsAt']=max($ends);$result[]=$warning;}
  return $result;
 }
 public static function settings(mixed $value,bool $strict=false):array{
  if($strict&&$value!==null&&!is_array($value))throw new \RuntimeException('Ungültige DWD-Anzeigeregel.',422);
  $input=is_array($value)?$value:[];$mode=$input['mode'] ?? 'continuous';$duration=$input['durationSeconds'] ?? 30;$interval=$input['intervalMinutes'] ?? 10;
  if(!in_array($mode,['continuous','once','interval'],true)||!is_int($duration)||$duration<1||$duration>86400||!is_int($interval)||$interval<1||$interval>10080||$mode==='interval'&&$duration>$interval*60){if($strict)throw new \RuntimeException('Bitte eine Anzeigedauer von 00:01 bis 1440:00 und ein passendes Wiederholungsintervall wählen.',422);return ['mode'=>'continuous','durationSeconds'=>30,'intervalMinutes'=>10];}
  return ['mode'=>$mode,'durationSeconds'=>$duration,'intervalMinutes'=>$interval];
 }
 public static function select(array $candidates,array $ledger,int $nowMs,bool $preview=false):array{
  $changed=false;$nextChangeAtMs=null;$boundary=static function(int $at)use(&$nextChangeAtMs,$nowMs):void{if($at>$nowMs&&($nextChangeAtMs===null||$at<$nextChangeAtMs))$nextChangeAtMs=$at;};
  foreach(self::grouped($candidates) as $warning){
   $rule=self::settings(($warning['source'] ?? '')==='dwd'?($warning['dwdPresentation'] ?? null):null);
   foreach($warning['warnings']??[] as $member){$at=self::timestamp($member['endsAt']??null);if($at!==null)$boundary($at);}
   if($rule['mode']==='continuous'){if(($warning['source']??'')==='dwd'){$at=self::timestamp($warning['endsAt']??null);if($at!==null)$boundary($at);$warning['presentationEndsAt']=$warning['endsAt']??null;}return ['warning'=>$warning,'ledger'=>$ledger,'changed'=>$changed,'nextChangeAtMs'=>$nextChangeAtMs];}
   $id=(string)$warning['id'];$key=json_encode([$rule,(string)($warning['dwdPresentationRevision'] ?? 'legacy')],JSON_UNESCAPED_SLASHES);$stored=$ledger[$id] ?? null;$members=isset($warning['warnings'])?array_map(static fn($item)=>(string)$item['id'],$warning['warnings']):null;
   $prior=is_array($stored)&&($stored['key']??'')===$key&&is_array($stored['memberIds']??null)?$stored['memberIds']:[];$unseen=$members!==null&&(bool)array_diff($members,$prior);
   $reset=!is_array($stored)||($stored['key']??'')!==$key||!is_int($stored['startedAtMs']??null)||$unseen;$start=$reset?$nowMs:$stored['startedAtMs'];
   if($reset&&!$preview){$ledger[$id]=['key'=>$key,'startedAtMs'=>$start];if($members!==null)$ledger[$id]['memberIds']=array_values(array_unique([...$prior,...$members]));$changed=true;}
   $duration=$rule['durationSeconds']*1000;$age=max(0,$nowMs-$start);$period=$rule['intervalMinutes']*60000;$cycle=$rule['mode']==='interval'?intdiv($age,$period):0;$phase=$rule['mode']==='interval'?$age%$period:$age;
   if($phase<$duration){$expires=self::timestamp($warning['endsAt']??null);$until=min($start+$cycle*$period+$duration,$expires??PHP_INT_MAX);$boundary($until);$warning['presentationEndsAt']=gmdate('Y-m-d\TH:i:s',intdiv($until,1000)).sprintf('.%03dZ',$until%1000);return ['warning'=>$warning,'ledger'=>$ledger,'changed'=>$changed,'nextChangeAtMs'=>$nextChangeAtMs];}
   if($rule['mode']==='interval')$boundary($start+($cycle+1)*$period);
  }
  return ['warning'=>null,'ledger'=>$ledger,'changed'=>$changed,'nextChangeAtMs'=>$nextChangeAtMs];
 }
}
