<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class PlayerHealth {
 public const DEFAULTS=['enabled'=>true,'offlineDelay'=>180,'mediaDelay'=>60,'staleDelay'=>120,'emptyDelay'=>120];
 private static function iso(int $ms):string{return gmdate('Y-m-d\TH:i:s',intdiv($ms,1000)).sprintf('.%03dZ',$ms%1000);}
 private static function ms(mixed $s):int{try{return is_string($s)&&$s!==''?(int)round((float)(new \DateTimeImmutable($s))->format('U.u')*1000):0;}catch(\Throwable){return 0;}}
 private static function seconds(mixed $v,int $max):?int{return (is_int($v)||is_float($v))&&is_finite((float)$v)&&$v>=0&&$v<=$max?(int)floor($v):null;}
 public static function settings(array $input=[],bool $strict=false):array{
  $out=self::DEFAULTS;foreach($out as $key=>$default){if(!array_key_exists($key,$input))continue;if($key==='enabled'){if(is_bool($input[$key]))$out[$key]=$input[$key];elseif($strict)throw new \RuntimeException('Überwachung muss ein- oder ausgeschaltet sein.',422);}else{$n=self::seconds($input[$key],86400);if($n!==null&&$n>=30)$out[$key]=$n;elseif($strict)throw new \RuntimeException('Wartezeiten müssen zwischen 30 und 86400 Sekunden liegen.',422);}}
  return $out+['updatedAt'=>is_string($input['updatedAt']??null)?$input['updatedAt']:null,'displays'=>is_array($input['displays']??null)?$input['displays']:[]];
 }
 public static function normalize(mixed $input,?array $previous=null,?int $now=null):?array{
  if(!is_array($input)||($input['schema']??null)!==1)return null;$now??=(int)round(microtime(true)*1000);$age=self::seconds($input['stateAgeSeconds']??null,604800);$failures=self::seconds($input['stateFailures']??null,1000)??0;$paused=($input['paused']??false)===true;$codes=[];
  foreach(array_slice(is_array($input['mediaErrors']??null)?$input['mediaErrors']:[],0,10) as $error){$code=is_array($error)?($error['code']??null):null;if(in_array($code,['IMAGE_LOAD_FAILED','VIDEO_LOAD_FAILED','VIDEO_DECODE_FAILED'],true)&&!in_array($code,$codes,true))$codes[]=$code;}
  $flags=['media'=>count($codes)>0,'stale'=>$failures>0||$age===null||$age>30,'empty'=>($input['hasContent']??null)===false&&$age!==null&&$age<=30&&$failures===0];$since=[];
  foreach($flags as $code=>$bad)if($bad&&!$paused){$old=$previous&&$now-self::ms($previous['receivedAt']??null)<=90000?($previous['since'][$code]??null):null;$since[$code]=$old&&self::ms($old)<=$now?$old:self::iso($now);}
  return ['schema'=>1,'receivedAt'=>self::iso($now),'stateAgeSeconds'=>$age,'stateFailures'=>$failures,'hasContent'=>is_bool($input['hasContent']??null)?$input['hasContent']:null,'paused'=>$paused,'mediaErrors'=>array_map(static fn($c)=>['code'=>$c],$codes),'since'=>$since,'lastFetchMs'=>self::seconds($input['lastFetchMs']??null,120000),'appVersion'=>is_string($input['appVersion']??null)&&preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}$/',$input['appVersion'])?$input['appVersion']:null];
 }
 public static function assess(array $display,?array $diagnostics,array $settings,string $version,?int $now=null):array{
  $now??=(int)round(microtime(true)*1000);$cfg=self::settings($settings);$control=$cfg['displays'][$display['id']]??[];$last=self::ms($display['lastSeenAt']??null);$seen=$last>0;$online=$seen&&$now-$last<=60000&&$now>=$last;
  $monitored=$cfg['enabled']&&!in_array($display['status']??'active',['disabled','archived'],true)&&empty($control['paused']);$telemetry=$diagnostics&&$now-self::ms($diagnostics['receivedAt']??null)<=90000?$diagnostics:null;$alerts=[];$pending=[];
  $labels=['offline'=>'Player nicht erreichbar','media'=>'Medien konnten nicht geladen werden','stale'=>'Inhalte werden nicht erfolgreich abgerufen','empty'=>'Player meldet keinen ausspielbaren Inhalt'];$cutoff=max(self::ms($cfg['updatedAt']),self::ms($control['changedAt']??null));
  $add=static function(string $code,int $start,int $delay)use($now,$cutoff,$labels,&$alerts,&$pending):void{$start=max($start,$cutoff);$item=['code'=>$code,'message'=>$labels[$code],'since'=>self::iso($start),'activeAt'=>self::iso($start+$delay*1000)];if($now>=$start+$delay*1000)$alerts[]=$item;else $pending[]=$item;};
  if($monitored&&$seen){if(!$online)$add('offline',$last,$cfg['offlineDelay']);elseif($telemetry&&empty($telemetry['paused']))foreach(['media','stale','empty'] as $code){$since=(array)($telemetry['since']??[]);$start=self::ms($since[$code]??null);if($start)$add($code,$start,$cfg[$code.'Delay']);}}
  $age=$telemetry['stateAgeSeconds']??null;$lastSuccess=is_int($age)?self::iso(self::ms($telemetry['receivedAt'])-$age*1000):null;$appVersion=$telemetry['appVersion']??null;$update=$appVersion!==null&&version_compare($appVersion,$version,'<');
  return ['id'=>$display['id'],'name'=>$display['name'],'location'=>$display['location']??'','locationId'=>$display['locationId']??null,'online'=>$online,'lastSeenAt'=>$display['lastSeenAt']??null,'playerVersion'=>$display['playerVersion']??$display['version']??null,'appVersion'=>$appVersion,'serverVersion'=>$version,'updateAvailable'=>$update,'lastSuccessfulStateAt'=>$lastSuccess,'lastFetchMs'=>$telemetry['lastFetchMs']??null,'telemetrySupported'=>$telemetry!==null,'mediaErrors'=>$telemetry['mediaErrors']??[],'monitored'=>$monitored,'paused'=>!empty($control['paused']),'background'=>($telemetry['paused']??false)===true,'status'=>!$monitored?'paused':(count($alerts)?'error':(count($pending)?'pending':(!$seen?'unpaired':(!$online?'offline':(!$telemetry?'unknown':(!empty($telemetry['paused'])?'background':'ok')))))),'alerts'=>$alerts,'pending'=>$pending];
 }
 public static function report(array $displays,array $settings,string $version):array{$cfg=self::settings($settings);$rows=array_map(static fn($d)=>self::assess($d,is_array($d['playerHealth']??null)?$d['playerHealth']:null,$cfg,$version),$displays);$cfg['displays']=(object)$cfg['displays'];return ['data'=>$rows,'settings'=>$cfg,'checkedAt'=>self::iso((int)round(microtime(true)*1000)),'activeAlerts'=>array_sum(array_map(static fn($d)=>count($d['alerts']),$rows))];}
 public static function metadata(array $body):array{
  $out=['lastSeenAt'=>gmdate('c'),'online'=>true,'cached'=>($body['cached']??false)===true];foreach(['channelId','slideId','version'] as $k)if(is_string($body[$k]??null)&&strlen($body[$k])<=200)$out[$k]=$body[$k];else $out[$k]=null;
  foreach(['viewportWidth','viewportHeight'] as $k){$n=self::seconds($body[$k]??null,50000);if($n!==null&&$n>=100)$out[$k]=$n;}
  $d=$body['devicePixelRatio']??null;if((is_int($d)||is_float($d))&&is_finite((float)$d)&&$d>=.25&&$d<=10)$out['devicePixelRatio']=$d;return $out;
 }
}
