<?php

declare(strict_types=1);
namespace Kiosky\Core;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/** External scheduling contract and calendar recurrence, shared by both CMSs. */
final class ExternalSchedule
{
    private static function fail(string $message): never { throw new RuntimeException($message, 422); }
    private static function text(mixed $value, int $max = 100): string
    {
        if (!is_string($value) || !trim($value) || strlen($value) > $max) self::fail('Pflichtfeld fehlt oder ist ungültig.');
        return trim($value);
    }
    private static function instant(mixed $value): string
    {
        $s = self::text($value);
        if (!preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d(?::\d\d(?:\.\d{1,3})?)?(?:Z|[+-]\d\d:\d\d)$/D', $s)) self::fail('ISO-Datum mit Zeitzonenoffset erforderlich.');
        try { $d = new DateTimeImmutable($s); } catch (Throwable) { self::fail('Ungültiger Zeitpunkt.'); }
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) self::fail('Ungültiger Zeitpunkt.');
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
    private static function wall(int $instant, DateTimeZone $zone): int
    {
        return (new DateTimeImmutable('@'.$instant))->setTimezone($zone)->getTimestamp() + $zone->getOffset(new DateTimeImmutable('@'.$instant));
    }
    private static function local(string $value, DateTimeZone $zone): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value, new DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d\TH:i:s') !== $value) self::fail('Ungültiges lokales Datum oder Uhrzeit.');
        $desired = $d->getTimestamp(); $candidates = [];
        foreach ([-36,-12,0,12,36] as $hours) {
            $sample = $desired + $hours * 3600;
            $instant = $desired - (self::wall($sample,$zone) - $sample);
            if (self::wall($instant,$zone) === $desired) $candidates[] = $instant;
        }
        if (!$candidates) self::fail('Diese lokale Uhrzeit existiert wegen der Zeitumstellung nicht.');
        return gmdate('Y-m-d\TH:i:s',min($candidates)).'.000Z';
    }
    public static function localTimestamp(string $value,string $timezone): string { return self::local($value,new DateTimeZone($timezone)); }
    public static function input(array $input, ?int $now = null): array
    {
        $target = $input['target'] ?? null; $content = $input['content'] ?? null; $timing = $input['timing'] ?? null;
        if (!is_array($target) || !is_array($content) || !is_array($timing)) self::fail('target, content und timing müssen Objekte sein.');
        if (!in_array($target['type'] ?? null,['display','group'],true) || !in_array($content['type'] ?? null,['channel','slide'],true)) self::fail('Ungültiger Ziel- oder Inhaltstyp.');
        $timezone = isset($timing['timezone']) ? self::text($timing['timezone']) : 'Europe/Berlin';
        if (!in_array($timezone,DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true)) self::fail('Ungültige IANA-Zeitzone.');
        $zone = new DateTimeZone($timezone); $recurrence = $timing['recurrence'] ?? 'once'; $interval = $timing['interval'] ?? 1; $weekdays = $timing['weekdays'] ?? [];
        if (!in_array($recurrence,['once','daily','weekly','interval'],true) || !is_int($interval) || $interval < 1 || $interval > 365) self::fail('Ungültige Wiederholung oder Intervall.');
        if (!is_array($weekdays) || !array_is_list($weekdays) || count(array_unique($weekdays,SORT_REGULAR)) !== count($weekdays)) self::fail('Ungültige Wochentage.');
        foreach ($weekdays as $day) if (!is_int($day) || $day < 0 || $day > 6) self::fail('Wochentage müssen zwischen 0 und 6 liegen.');
        if (($timing['mode'] ?? '') === 'time') {
            $date = ($timing['date'] ?? '') !== '' ? self::text($timing['date']) : gmdate('Y-m-d',self::wall($now ?? time(),$zone));
            if (!preg_match('/^\d{4}-\d\d-\d\d$/D',$date)) self::fail('date muss YYYY-MM-DD sein.');
            $clock = static function(mixed $v): string { $s=self::text($v); if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D',$s))self::fail('Ungültige Uhrzeit.');return strlen($s)===5?$s.':00':$s; };
            $a=$clock($timing['startTime'] ?? null);$b=$clock($timing['endTime'] ?? null);
            if ($a === $b) self::fail('Start- und Endzeit müssen verschieden sein.');
            $startsAt = self::local($date.'T'.$a,$zone);
            $endDate = $b < $a ? gmdate('Y-m-d',strtotime($date.'T00:00:00Z')+86400) : $date;
            $endsAt = self::local($endDate.'T'.$b,$zone);
        } elseif (($timing['mode'] ?? '') === 'datetime') {
            $startsAt=self::instant($timing['startsAt'] ?? null);$endsAt=self::instant($timing['endsAt'] ?? null);
        } else self::fail('timing.mode muss time oder datetime sein.');
        if ($endsAt <= $startsAt) self::fail('Das Ende muss nach dem Start liegen.');
        if ($recurrence !== 'once' && self::wall(strtotime($endsAt),$zone)-self::wall(strtotime($startsAt),$zone)>=86400) self::fail('Wiederkehrende Zeitfenster müssen kürzer als 24 Stunden sein.');
        $from=empty($timing['from'])?null:self::instant($timing['from']);$until=empty($timing['until'])?null:self::instant($timing['until']);
        if ($until && $until <= ($from ?? $startsAt)) self::fail('until muss nach dem Beginn liegen.');
        $priority=$input['priority'] ?? 'normal';
        if (!in_array($priority,['normal','high','takeover'],true)) self::fail('Ungültige Priorität.');
        if (isset($input['active']) && !is_bool($input['active'])) self::fail('active muss ein Boolean sein.');
        if (isset($input['description']) && (!is_string($input['description']) || strlen($input['description'])>2000)) self::fail('description ist ungültig.');
        $id=self::text($content['id'] ?? null);$type=$content['type'];
        return ['targetType'=>$target['type'],'targetId'=>self::text($target['id'] ?? null),'contentType'=>$type,'contentId'=>$id,'channelId'=>$type==='channel'?$id:null,'slideId'=>$type==='slide'?$id:null,'startsAt'=>$startsAt,'endsAt'=>$endsAt,'recurrence'=>$recurrence,'recurrenceTimezone'=>$timezone,'recurrenceInterval'=>$interval,'recurrenceWeekdays'=>$weekdays,'recurrenceStartsAt'=>$from,'recurrenceEndsAt'=>$until,'priority'=>$priority,'status'=>'published','active'=>$input['active'] ?? true,'description'=>$input['description'] ?? ''];
    }
    public static function active(array $s, string $nowValue): bool
    {
        if (($s['active'] ?? true) === false) return false;
        $now=strtotime($nowValue);$start=strtotime((string)($s['startsAt'] ?? ''));$end=strtotime((string)($s['endsAt'] ?? ''));
        if ($now===false || $start===false || $end===false || $end<=$start) return false;
        $recurrence=$s['recurrence'] ?? 'once';
        if ($recurrence==='once') return $start<=$now && $now<$end;
        if ($now<$start || (!empty($s['recurrenceStartsAt']) && $now<strtotime($s['recurrenceStartsAt'])) || (!empty($s['recurrenceEndsAt']) && $now>=strtotime($s['recurrenceEndsAt']))) return false;
        try { $zone=new DateTimeZone($s['recurrenceTimezone'] ?? 'UTC'); } catch(Throwable) { return false; }
        $a=self::wall($start,$zone);$b=self::wall($end,$zone);$n=self::wall($now,$zone);$anchor=(int)floor($a/86400);$today=(int)floor($n/86400);$duration=$b-$a;$interval=max(1,(int)($s['recurrenceInterval'] ?? 1));
        $days=!empty($s['recurrenceWeekdays'])?$s['recurrenceWeekdays']:[(int)gmdate('w',$a)];
        for($back=0;$back<=min(366,(int)ceil($duration/86400));$back++) {
            $candidateDay=$today-$back;$elapsed=$candidateDay-$anchor;
            if($elapsed<0)continue;
            if($recurrence==='weekly' && (!in_array((int)gmdate('w',$candidateDay*86400),$days,true) || (int)floor($elapsed/7)%$interval))continue;
            if($recurrence!=='weekly' && $elapsed%$interval)continue;
            $candidate=$candidateDay*86400+$a%86400;
            try {
                $actualStart=strtotime(self::local(gmdate('Y-m-d\TH:i:s',$candidate),$zone));
                $actualEnd=strtotime(self::local(gmdate('Y-m-d\TH:i:s',$candidate+$duration),$zone));
                if($now>=$actualStart && $now<$actualEnd)return true;
            } catch(Throwable) { /* Missing local DST occurrence. */ }
        }
        return false;
    }

    /** Most recent occurrence, including a completed slot missed by an offline player. */
    public static function latestStart(array $s, float $now): ?float
    {
        $start=strtotime((string)($s['startsAt'] ?? ''));$end=strtotime((string)($s['endsAt'] ?? ''));
        if(($s['active'] ?? true)===false||$start===false||$end===false||$end<=$start||$now<$start)return null;
        $recurrence=$s['recurrence'] ?? 'once';if($recurrence==='once')return (float)$start;
        $from=empty($s['recurrenceStartsAt'])?$start:strtotime($s['recurrenceStartsAt']);
        $until=empty($s['recurrenceEndsAt'])?INF:strtotime($s['recurrenceEndsAt']);$limit=min($now,$until-0.001);
        if($limit<$from)return null;
        try{
            $zone=new DateTimeZone($s['recurrenceTimezone'] ?? 'UTC');$a=self::wall($start,$zone);$duration=self::wall($end,$zone)-$a;
            $anchor=(int)floor($a/86400);$today=(int)floor(self::wall((int)$limit,$zone)/86400);$weekly=$recurrence==='weekly';
            $interval=max(1,(int)($s['recurrenceInterval'] ?? 1));$cycleDays=$interval*($weekly?7:1);
            $days=!empty($s['recurrenceWeekdays'])?$s['recurrenceWeekdays']:[(int)gmdate('w',$a)];
            $cycle=(int)floor(($today-$anchor)/$cycleDays);
            for($attempt=0;$attempt<3&&$cycle>=0;$attempt++,$cycle--){
                for($offset=$weekly?6:0;$offset>=0;$offset--){
                    $candidateDay=$anchor+$cycle*$cycleDays+$offset;
                    if($candidateDay>$today||($weekly&&!in_array((int)gmdate('w',$candidateDay*86400),$days,true)))continue;
                    $candidate=$candidateDay*86400+$a%86400;
                    try{
                        $actualStart=strtotime(self::local(gmdate('Y-m-d\TH:i:s',$candidate),$zone));
                        $actualEnd=strtotime(self::local(gmdate('Y-m-d\TH:i:s',$candidate+$duration),$zone));
                        $effective=max($start,$from,$actualStart);
                        if($effective<=$limit&&$actualEnd>$effective)return (float)$effective;
                    }catch(Throwable){/* Missing local DST occurrence. */}
                }
            }
        }catch(Throwable){/* Invalid legacy recurrence. */}
        return null;
    }

    /** First actual trigger at or after a point, using local calendar recurrence. */
    public static function nextStart(array $s, float $after): ?float
    {
        try {
            $start=(float)(new DateTimeImmutable((string)$s['startsAt']))->format('U.u');
            if (($s['active'] ?? true)===false) return null;
            if (($s['recurrence'] ?? 'once')==='once') return $start>=$after?$start:null;
            $from=empty($s['recurrenceStartsAt'])?$start:(float)(new DateTimeImmutable($s['recurrenceStartsAt']))->format('U.u');
            $until=empty($s['recurrenceEndsAt'])?INF:(float)(new DateTimeImmutable($s['recurrenceEndsAt']))->format('U.u');
            $lower=max($after,$start,$from);if($lower>=$until)return null;
            $zone=new DateTimeZone($s['recurrenceTimezone'] ?? 'UTC');$a=self::wall((int)$start,$zone);$anchor=(int)floor($a/86400);$today=(int)floor(self::wall((int)$lower,$zone)/86400);
            $weekly=($s['recurrence'] ?? '')==='weekly';$interval=max(1,(int)($s['recurrenceInterval'] ?? 1));$cycleDays=$interval*($weekly?7:1);
            $weekdays=!empty($s['recurrenceWeekdays'])?$s['recurrenceWeekdays']:[(int)gmdate('w',$a)];$first=max(0,(int)floor(($today-$anchor)/$cycleDays));
            for($cycle=$first;$cycle<=$first+2;$cycle++)for($offset=0;$offset<($weekly?7:1);$offset++){
                $candidateDay=$anchor+$cycle*$cycleDays+$offset;
                if($weekly&&!in_array((int)gmdate('w',$candidateDay*86400),$weekdays,true))continue;
                try{$actual=(float)(new DateTimeImmutable(self::local(gmdate('Y-m-d\TH:i:s',$candidateDay*86400+$a%86400),$zone)))->format('U.u')+($start-floor($start));if($actual>=$lower&&$actual<$until)return $actual;}catch(Throwable){}
            }
        } catch(Throwable) {}
        return null;
    }
}
