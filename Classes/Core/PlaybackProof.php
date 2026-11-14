<?php
declare(strict_types=1);
namespace Kiosky\Core;
require_once __DIR__.'/PlaybackProofStore.php';
final class PlaybackProof {
 private static function text(string $value):string{preg_match('/^.{0,300}/us',$value,$m);return $m[0]??'';}
 private static function encode(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
 private static function decode(string $value):string{return base64_decode(strtr($value,'-_','+/'));}
 private static function sign(string $payload,string $key):string{return self::encode(hash_hmac('sha256','kiosky-proof-v1:'.$payload,$key,true));}
 public static function grants(string $displayId,?array $channel,array $slides,string $key,?int $now=null):array{
  if($key==='')return [];$at=($now??time())*1000;$grants=[];
  foreach($slides as $item){$slide=$item['slide']??null;if(!$slide||!(str_starts_with($channel['automationKey']??'','advertising:channel')||array_filter($slide['tags']??[],static fn($v):bool=>(bool)preg_match('/^werbung(?:\s|$)/i',(string)$v))))continue;
   $payload=self::encode(json_encode(['displayId'=>$displayId,'slideId'=>$slide['id'],'channelId'=>$channel['id']??'','slideName'=>self::text((string)($slide['name']??'')),'channelName'=>self::text((string)($channel['name']??'')),'issuedAt'=>(int)floor($at/3600000)*3600000,'expiresAt'=>(int)floor($at/3600000)*3600000+90*86400000],JSON_THROW_ON_ERROR));$grants[$slide['id']]=$payload.'.'.self::sign($payload,$key);
  }return $grants;
 }
 public static function receipt(string $displayId,array $input,string $key,?int $now=null):?array{
  try{
   if($key===''||!is_string($input['grant']??null)||strlen($input['grant'])>5000||!preg_match('/^[-a-zA-Z0-9_]{8,100}$/',(string)($input['id']??''))||!preg_match('/^[-a-zA-Z0-9_]{8,100}$/',(string)($input['playId']??'')))return null;
   $parts=explode('.',$input['grant']);if(count($parts)!==2||!hash_equals(self::sign($parts[0],$key),$parts[1]))return null;
   $grant=json_decode(self::decode($parts[0]),true,512,JSON_THROW_ON_ERROR);$start=isset($input['startedAt'])?(float)(new \DateTimeImmutable($input['startedAt']))->format('U.u'):false;$end=isset($input['endedAt'])?(float)(new \DateTimeImmutable($input['endedAt']))->format('U.u'):false;$seconds=is_numeric($input['durationMs']??null)?(float)$input['durationMs']/1000:NAN;$at=$now??time();
   if(($grant['displayId']??'')!==$displayId||$start===false||$end===false||$end<=$start||!is_finite($seconds)||$seconds<1||$seconds>60||$seconds>$end-$start+.01||$end-$start>65||$start*1000<$grant['issuedAt']-120000||$end*1000>$grant['expiresAt']||$end>$at+60||$end<$at-90*86400)return null;
   return ['displayId'=>$displayId,'id'=>$input['id'],'playId'=>$input['playId'],'slideId'=>$grant['slideId'],'channelId'=>$grant['channelId'],'slideName'=>$grant['slideName'],'channelName'=>$grant['channelName'],'startedAt'=>(new \DateTimeImmutable($input['startedAt']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),'endedAt'=>(new \DateTimeImmutable($input['endedAt']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),'seconds'=>$seconds,'receivedAt'=>gmdate('Y-m-d\TH:i:s',$at).'.000Z'];
  }catch(\Throwable){return null;}
 }
 public static function period(mixed $from,mixed $until):array{
  $a=is_string($from)?strtotime($from):false;$b=is_string($until)?strtotime($until):false;
  if($a===false||$b===false||$b<=$a||$b-$a>93*86400)throw new \RuntimeException('Bitte einen gültigen Berichtszeitraum von höchstens 93 Tagen auswählen.',422);
  return [gmdate('Y-m-d\TH:i:s',$a).'.000Z',gmdate('Y-m-d\TH:i:s',$b).'.000Z'];
 }
}
