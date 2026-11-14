<?php
declare(strict_types=1);
namespace Kiosky\Core;
require_once __DIR__.'/ExternalSchedule.php';
final class DisplaySimulation {
 public static function arguments(array $body):array{$at=is_string($body['at']??null)?strtotime($body['at']):false;$hours=$body['hours']??12;if($at===false||!is_numeric($hours)||(float)$hours<0||(float)$hours>24)throw new \RuntimeException('Bitte einen gültigen Zeitpunkt und höchstens 24 Stunden auswählen.',422);return [$at,$at+(float)$hours*3600];}
 public static function boundary(float $cursor,float $until,array $schedules,array $times):float{
  $next=$until;$add=static function(?float $time)use(&$next,$cursor):void{if($time!==null&&is_finite($time)&&$time>$cursor&&$time<$next)$next=$time;};
  foreach($times as $time){$n=is_string($time)?strtotime($time):false;if($n!==false)$add((float)$n);}
  foreach($schedules as $s){if(($s['status']??'')!=='published'||($s['active']??true)===false)continue;
   $add(ExternalSchedule::nextStart($s,$cursor+.001));if(!empty($s['recurrenceEndsAt']))$add((float)strtotime($s['recurrenceEndsAt']));$last=ExternalSchedule::latestStart($s,$cursor);if($last===null)continue;
   try{if(($s['recurrence']??'once')==='once'){$add((float)strtotime($s['endsAt']));continue;}
    $zone=new \DateTimeZone($s['recurrenceTimezone']??'UTC');$wall=static fn(string $time):int=>strtotime((new \DateTimeImmutable($time))->setTimezone($zone)->format('Y-m-d\TH:i:s').'Z');
    $local=$wall(gmdate('c',(int)$last))+$wall($s['endsAt'])-$wall($s['startsAt']);$add((float)strtotime(ExternalSchedule::localTimestamp(gmdate('Y-m-d\TH:i:s',$local),$zone->getName())));
   }catch(\Throwable){}
  }return $next;
 }
}
