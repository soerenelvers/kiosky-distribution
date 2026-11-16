<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;
require_once __DIR__.'/PresetTriggers.php';
/** Virtual, bounded event schedules; manual schedules are never rewritten. */
final class EventAutomation
{
    public const ANCHORS=['setupStart','admissionStart','eventStart','breakStart','breakEnd','eventEnd'];
    public static function empty():array{return ['revision'=>0,'templates'=>[],'bindings'=>[],'runs'=>[],'observations'=>[]];}
    private static function text(mixed $value,string $name,int $max=200):string{if(!is_string($value)||trim($value)===''||strlen($value)>$max)throw new RuntimeException($name.' fehlt oder ist ungültig.',422);return trim($value);}
    private static function number(mixed $value,int $min,int $max):int{if((!is_int($value)&&!is_float($value))||floor((float)$value)!=$value||$value<$min||$value>$max)throw new RuntimeException('Zeitangabe außerhalb des erlaubten Bereichs.',422);return (int)$value;}
    public static function time(mixed $value):?float{if(!is_string($value)||!preg_match('/^\d{4}-\d\d-\d\dT/',$value))return null;try{if(preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d(?::\d\d)?$/',$value))$value=ExternalSchedule::localTimestamp(strlen($value)===16?$value.':00':$value,'Europe/Berlin');$date=new \DateTimeImmutable($value,new \DateTimeZone('Europe/Berlin'));$errors=\DateTimeImmutable::getLastErrors();if($errors&&($errors['warning_count']||$errors['error_count']))return null;return (float)$date->format('U.u');}catch(\Throwable){return null;}}
    public static function template(array $body,?string $id=null):array{
        if(!is_array($body['steps']??null)||!array_is_list($body['steps'])||count($body['steps'])<1||count($body['steps'])>20)throw new RuntimeException('Bitte einen bis zwanzig Ablaufschritte anlegen.',422);
        $steps=[];$seen=[];foreach($body['steps'] as $step){
            if(!is_array($step)||!in_array($step['anchor']??null,self::ANCHORS,true)||!empty($step['untilAnchor'])&&!in_array($step['untilAnchor'],self::ANCHORS,true))throw new RuntimeException('Ungültiger Veranstaltungs-Zeitbezug.',422);
            $key=array_key_exists('id',$step)?self::text($step['id'],'Schrittkennung',100):bin2hex(random_bytes(16));if(isset($seen[$key]))throw new RuntimeException('Doppelte Schrittkennung.',422);$seen[$key]=true;
            if(!in_array($step['content']['type']??null,['slide','channel'],true))throw new RuntimeException('Bitte einen Slide oder Kanal auswählen.',422);
            $steps[]=['id'=>$key,'name'=>self::text($step['name']??null,'Schrittname'),'anchor'=>$step['anchor'],'offsetSeconds'=>self::number($step['offsetSeconds']??0,-86400,86400),'durationSeconds'=>self::number($step['durationSeconds']??1800,1,86400),'untilAnchor'=>$step['untilAnchor']??null,'untilOffsetSeconds'=>self::number($step['untilOffsetSeconds']??0,-86400,86400),'content'=>['type'=>$step['content']['type'],'id'=>self::text($step['content']['id']??null,'Inhalt',100)]];
        }return ['id'=>$id??bin2hex(random_bytes(16)),'name'=>self::text($body['name']??null,'Vorlagenname'),'steps'=>$steps];
    }
    private static function binding(array $body,?string $id):array{
        if(!is_array($body['targets']??null)||!array_is_list($body['targets'])||count($body['targets'])<1||count($body['targets'])>50)throw new RuntimeException('Bitte ein bis fünfzig Ziele auswählen.',422);
        foreach(['enabled','includeCancelled'] as $key)if(array_key_exists($key,$body)&&!is_bool($body[$key]))throw new RuntimeException('Ungültiger Schalter.',422);
        $targets=[];$seen=[];foreach($body['targets'] as $target){if(!is_array($target)||!in_array($target['type']??null,['display','group','matrix'],true))throw new RuntimeException('Ungültiges Ausspielziel.',422);$target=['type'=>$target['type'],'id'=>self::text($target['id']??null,'Ziel',100)];$key=$target['type'].':'.$target['id'];if(isset($seen[$key]))throw new RuntimeException('Doppeltes Ausspielziel.',422);$seen[$key]=true;$targets[]=$target;}
        return ['id'=>$id??bin2hex(random_bytes(16)),'templateId'=>self::text($body['templateId']??null,'Ablaufvorlage',100),'eventId'=>self::text($body['eventId']??null,'Veranstaltung',100),'enabled'=>($body['enabled']??true)!==false,'includeCancelled'=>($body['includeCancelled']??false)===true,'targets'=>$targets];
    }
    public static function plans(array $config,array $events):array{
        $events=array_column($events,null,'id');$templates=array_column($config['templates']??[],null,'id');$schedules=[];$diagnostics=[];
        foreach($config['bindings']??[] as $binding){if(empty($binding['enabled']))continue;$event=$events[$binding['eventId']]??null;$template=$templates[$binding['templateId']]??null;
            $reason=!$event?'Veranstaltung fehlt.':(!$template?'Vorlage fehlt.':(!empty($event['deletedAt'])||($event['status']??'')==='removed'?'Veranstaltung wurde entfernt.':(($event['status']??'')==='archived'&&empty($event['autoArchived'])?'Veranstaltung wurde manuell archiviert.':(!in_array(($event['status']??'')==='archived'?($event['autoArchivedFrom']??([2=>'cancelled',3=>'draft'][(int)($event['autoArchived']??0)]??'published')):($event['status']??''),['published','archived',...(!empty($binding['includeCancelled'])?['cancelled']:[])],true)?'Veranstaltung ist nicht veröffentlicht oder abgesagt.':''))));
            if($reason!==''){$diagnostics[]=['bindingId'=>$binding['id'],'eventId'=>$binding['eventId'],'reason'=>$reason];continue;}
            foreach($template['steps'] as $step){$start=self::time($event[$step['anchor']]??null);$end=!empty($step['untilAnchor'])?self::time($event[$step['untilAnchor']]??null):null;
                if($start!==null)$start+=$step['offsetSeconds'];$end=!empty($step['untilAnchor'])?($end===null?null:$end+$step['untilOffsetSeconds']):($start===null?null:$start+$step['durationSeconds']);
                if($start===null||$end===null||$end<=$start||$end-$start>86400){$diagnostics[]=['bindingId'=>$binding['id'],'stepId'=>$step['id'],'reason'=>'Zeitbezug fehlt oder das Zeitfenster ist ungültig (höchstens 24 Stunden).'];continue;}
                foreach($binding['targets'] as $target){if(count($schedules)>=10000)throw new RuntimeException('Zu viele automatische Ausspielwege. Bitte die Abläufe aufteilen.',422);
                    $row=['id'=>'event-flow:'.$binding['id'].':'.$step['id'].':'.$target['type'].':'.$target['id'],'bindingId'=>$binding['id'],'stepId'=>$step['id'],'templateId'=>$template['id'],'eventId'=>$event['id'],'eventTitle'=>$event['title'],'ruleName'=>$template['name'].' · '.$step['name'],'trigger'=>$step['anchor'],'offsetSeconds'=>$step['offsetSeconds'],'targetType'=>$target['type'],'targetId'=>$target['id'],'contentType'=>$step['content']['type'],'contentId'=>$step['content']['id'],'contentName'=>$step['contentName']??$step['content']['id'],'startsAt'=>PresetTriggers::stamp($start),'endsAt'=>PresetTriggers::stamp($end),'recurrence'=>'once','priority'=>'normal','stackOrder'=>-1,'status'=>'published','active'=>true,'createdAt'=>$binding['updatedAt'],'updatedAt'=>$binding['updatedAt'],'publishedAt'=>$binding['updatedAt']];$row[$step['content']['type']==='slide'?'slideId':'channelId']=$step['content']['id'];$schedules[]=$row;
                }
            }
        }return ['schedules'=>$schedules,'diagnostics'=>$diagnostics];
    }
    public static function observe(array &$config,array $plans,array $display,array $output,float $at):bool{
        $previous=$config['observations'][$display['id']]??[];$next=[];$changed=false;$record=static function(array $row)use(&$config,&$changed,$at,$display):void{$config['runs'][]=array_merge(['id'=>bin2hex(random_bytes(16)),'at'=>PresetTriggers::stamp($at),'displayId'=>$display['id'],'displayName'=>$display['name']],$row);$config['runs']=array_slice($config['runs'],-500);$changed=true;};
        foreach($plans as $p){$match=$p['targetType']==='display'&&$p['targetId']===$display['id']||$p['targetType']==='group'&&in_array($p['targetId'],$display['groupIds']??[],true)||$p['targetType']==='matrix'&&$p['targetId']===($display['matrix']['id']??$display['matrixId']??null);if(!$match||self::time($p['startsAt'])>$at||self::time($p['endsAt'])<=$at)continue;
            $reason=$output['selectionReason']??[];$status=($reason['kind']??'')==='schedule'&&($reason['scheduleId']??'')===$p['id']?'selected':'overridden';$why=$status==='selected'?'Ablaufschritt ist aktiv; der Inhalt wurde vom Server ausgewählt.':(['warning'=>'Eine Warnung hat Vorrang.','assignment'=>'Ein Preset oder eine direkte Zuweisung hat Vorrang.','schedule'=>'Eine manuelle Zeitplanung oder ein anderer Ablauf hat Vorrang.','default'=>'Der geplante Inhalt ist leer, nicht freigegeben oder aktuell nicht verfügbar; der Standardinhalt greift.'][$reason['kind']??'default']??'Eine andere Ausspielung wurde ausgewählt.');$selected=$output['warning']['title']??$output['channel']['name']??$output['directSlide']['name']??'Kein Inhalt';$signature=json_encode([$p['startsAt'],$p['endsAt'],$p['contentId'],$status,$why,$selected]);
            $row=array_intersect_key($p,array_flip(['bindingId','stepId','eventId','eventTitle','ruleName','startsAt','endsAt','targetType','targetId','contentType','contentId','contentName','trigger','offsetSeconds']))+['status'=>$status,'why'=>$why,'selected'=>$selected];$next[$p['id']]=['signature'=>$signature,'row'=>$row];if(($previous[$p['id']]['signature']??null)!==$signature)$record($row);
        }
        foreach($previous as $id=>$item)if(!isset($next[$id]))$record(array_replace($item['row'],['status'=>'ended','why'=>'Zeitfenster beendet oder Ablauf deaktiviert bzw. geändert.']));
        if($changed){if($next)$config['observations'][$display['id']]=$next;else unset($config['observations'][$display['id']]);}return $changed;
    }
    public static function request(array &$config,string $method,string $path,array $body,array $events,array $catalog,float $at):array{
        $config=array_replace(self::empty(),$config);
        if($path==='/api/event-automation'&&$method==='GET'){$result=$config;unset($result['observations']);$public=[];foreach($catalog as $key=>$rows)$public[$key]=array_map(static fn(array $r):array=>array_intersect_key($r,array_flip(['id','name','status'])),$rows);return $result+self::plans($config,$events)+['catalog'=>$public,'events'=>array_map(static fn(array $e):array=>array_intersect_key($e,array_flip(['id','title','date','status'])),$events),'readOnlySimulation'=>true];}
        if(!preg_match('#^/api/event-automation/(templates|bindings)(?:/([^/]+))?$#',$path,$match))throw new RuntimeException('Automationsroute nicht gefunden.',404);
        $collection=$match[1];$id=isset($match[2])?rawurldecode($match[2]):null;
        if(!in_array($method,['POST','PUT','DELETE'],true)||$method==='POST'&&$id!==null||$method!=='POST'&&$id===null)throw new RuntimeException('Methode nicht erlaubt.',405);
        if(($body['revision']??null)!==$config['revision'])throw new RuntimeException('Die Abläufe wurden zwischenzeitlich geändert. Bitte neu laden.',409);
        $rows=$config[$collection];$current=$id===null?null:(array_column($rows,null,'id')[$id]??null);if($id!==null&&!$current)throw new RuntimeException('Ablauf wurde nicht gefunden.',404);
        if($method==='DELETE'){if($collection==='templates')foreach($config['bindings'] as $binding)if($binding['templateId']===$id)throw new RuntimeException('Diese Vorlage wird noch verwendet. Bitte zuerst die zugehörigen Veranstaltungsabläufe entfernen.',422);$config[$collection]=array_values(array_filter($rows,static fn(array $r):bool=>$r['id']!==$id));}
        else{$item=$collection==='templates'?self::template($body,$id):self::binding($body,$id);
            if($collection==='templates'){foreach($item['steps'] as &$step){$found=array_column($catalog[$step['content']['type']==='slide'?'slides':'channels']??[],null,'id')[$step['content']['id']]??null;if(!$found||!empty($found['deletedAt'])||in_array($found['status']??'',['archived','removed'],true))throw new RuntimeException('Ein ausgewählter Inhalt ist nicht mehr verfügbar.',422);$step['contentName']=$found['name']??$found['id'];}unset($step);}
            else{if(!isset(array_column($config['templates'],null,'id')[$item['templateId']])||!isset(array_column($events,null,'id')[$item['eventId']])||!empty(array_column($events,null,'id')[$item['eventId']]['deletedAt'])||(array_column($events,null,'id')[$item['eventId']]['status']??'')==='removed')throw new RuntimeException('Veranstaltung oder Vorlage wurde nicht gefunden.',422);foreach($item['targets'] as $target)if(!isset(array_column($catalog[['display'=>'displays','group'=>'groups','matrix'=>'matrices'][$target['type']]]??[],null,'id')[$target['id']]))throw new RuntimeException('Ausspielziel wurde nicht gefunden.',422);}
            if(!$current&&count($rows)>=($collection==='templates'?100:200))throw new RuntimeException('Die maximale Anzahl von Vorlagen oder Abläufen ist erreicht.',422);
            $item['createdAt']=$current['createdAt']??PresetTriggers::stamp($at);$item['updatedAt']=PresetTriggers::stamp($at);if($current)$config[$collection]=array_map(static fn(array $r):array=>$r['id']===$id?$item:$r,$rows);else $config[$collection][]=$item;
        }
        self::plans($config,$events);$config['revision']++;return ['ok'=>true,'revision'=>$config['revision']];
    }
    public static function validateStorage(array $value,array $events):void{
        if(!is_array($value['templates']??null)||!array_is_list($value['templates'])||!is_array($value['bindings']??null)||!array_is_list($value['bindings'])||count($value['templates'])>100||count($value['bindings'])>200)throw new RuntimeException('Ungültige Veranstaltungsabläufe im Umzugspaket.',422);
        $seen=[];foreach($value['templates'] as $t){$id=self::text($t['id']??null,'Vorlagenkennung',100);if(isset($seen[$id]))throw new RuntimeException('Doppelte Vorlagenkennung.',422);$seen[$id]=true;self::template($t,$id);}
        $ids=[];foreach($value['bindings'] as $b){$id=self::text($b['id']??null,'Ablaufkennung',100);if(isset($ids[$id])||!isset($seen[$b['templateId']??'']))throw new RuntimeException('Doppelte Ablaufkennung oder fehlende Vorlage.',422);$ids[$id]=true;self::binding($b,$id);}
        self::plans($value,$events);if(!is_int($value['revision']??null)||$value['revision']<0||!is_array($value['runs']??null)||count($value['runs'])>500||!is_array($value['observations']??null)||count($value['observations'])>10000)throw new RuntimeException('Ungültiges Ablaufprotokoll.',422);
    }

}
