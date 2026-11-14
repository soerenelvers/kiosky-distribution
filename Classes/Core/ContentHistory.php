<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class ContentHistory {
 public const COLLECTIONS=['slide'=>'slides','channel'=>'channels','event'=>'events','schedule'=>'schedules'];
 public static function token(?array $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
 private static function text(string $value):string{preg_match('/^.{0,300}/us',$value,$m);return $m[0]??'';}
 private static function decode(string $value):?array{try{$plain=str_starts_with($value,'plain:')?base64_decode(substr($value,6)):(function_exists('gzinflate')?gzinflate(base64_decode($value),4000000):false);return is_string($plain)?json_decode($plain,true,512,JSON_THROW_ON_ERROR):null;}catch(\Throwable){return null;}}
 private static function snapshot(array $row):array{if(isset($row['document']))$row['document']=AdvertisingService::stripOverviewRuntime($row['document']);return $row;}
 private static function add(array &$rows,string $type,string $id,?array $snapshot,string $actor,string $action):void{
  $snapshot=$snapshot===null?null:self::snapshot($snapshot);$digest=self::token($snapshot);
  for($i=count($rows)-1;$i>=0;$i--)if($rows[$i]['entityType']===$type&&$rows[$i]['entityId']===$id){if($rows[$i]['digest']===$digest)return;break;}
  $serialized=$snapshot===null?null:json_encode($snapshot,JSON_THROW_ON_ERROR);$encoded=$serialized!==null&&strlen($serialized)<=4000000?(function_exists('gzdeflate')?base64_encode(gzdeflate($serialized)):'plain:'.base64_encode($serialized)):null;
  $previous=[];for($i=count($rows)-1;$i>=0;$i--)if($rows[$i]['entityType']===$type&&$rows[$i]['entityId']===$id&&!empty($rows[$i]['snapshot'])){$previous=self::decode($rows[$i]['snapshot'])??[];break;}
  $fields=array_values(array_filter(array_unique([...array_keys($previous),...array_keys($snapshot??[])]),static fn(string $k):bool=>!in_array($k,['updatedAt','createdAt','currentVersion','lastSyncedAt','sourceUpdatedAt','importHash'],true)&&($previous[$k]??null)!==($snapshot[$k]??null)));
  $rows[]=['fields'=>$fields,'id'=>bin2hex(random_bytes(16)),'entityType'=>$type,'entityId'=>$id,'actorId'=>$actor,'createdAt'=>gmdate('c'),'action'=>$action,'title'=>self::text((string)($snapshot['name']??$snapshot['title']??'Zeitzuweisung')),'digest'=>$digest,'snapshot'=>$encoded!==null&&strlen($encoded)<=262144?$encoded:null];
 }
 public static function capture(array &$state,array $before,string $actor):void{
  $rows=$state['contentHistory']??[];
  foreach(self::COLLECTIONS as $type=>$collection){$old=array_column($before[$collection]??[],null,'id');$new=array_column($state[$collection]??[],null,'id');
   foreach(array_unique([...array_keys($old),...array_keys($new)]) as $id){$a=$old[$id]??null;$b=$new[$id]??null;if($a===$b)continue;
    if(($type==='event'&&(!$b||($b['status']??'')==='removed'||!empty($b['deletedAt'])))||(!$b&&$type!=='schedule')){$rows=array_values(array_filter($rows,static fn(array $r):bool=>$r['entityType']!==$type||$r['entityId']!==$id));continue;}
    if($a&&!array_filter($rows,static fn(array $r):bool=>$r['entityType']===$type&&$r['entityId']===$id))self::add($rows,$type,$id,$a,'unknown','baseline');
    self::add($rows,$type,$id,$b,$actor,$b?'saved':'deleted');
   }
  }
  $kept=[];$counts=[];$bytes=0;
  foreach(array_reverse($rows) as $r){$key=$r['entityType'].':'.$r['entityId'];$size=strlen($r['snapshot']??'');if(($counts[$key]??0)>=20||count($kept)>=1000||$bytes+$size>8*1024*1024)continue;$counts[$key]=($counts[$key]??0)+1;$bytes+=$size;$kept[]=$r;}
  $state['contentHistory']=array_reverse($kept);
 }
 public static function listing(array $state,string $type,?string $id=null):array{
  return array_map(static function(array $r):array{$r['restorable']=$r['snapshot']!==null;$r['actorName']=$r['actorId']==='system'?'System':($r['actorId']==='unknown'?'Vor Einführung des Verlaufs':$r['actorId']);unset($r['snapshot'],$r['digest']);return $r;},array_slice(array_values(array_filter(array_reverse($state['contentHistory']??[]),static fn(array $r):bool=>$r['entityType']===$type&&($id===null||$r['entityId']===$id))),0,100));
 }
 public static function revision(array $state,string $type,string $id,string $revision):array{
  foreach($state['contentHistory']??[] as $r)if($r['entityType']===$type&&$r['entityId']===$id&&$r['id']===$revision&&!empty($r['snapshot'])){$body=self::decode($r['snapshot']);if(is_array($body))return $body;}
  throw new \RuntimeException('Dieser Stand ist nicht wiederherstellbar.',404);
 }
}
