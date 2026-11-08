<?php
declare(strict_types=1);
namespace Kiosky\Core;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
require_once __DIR__.'/PresetTriggers.php';
final class PresetTimerActions
{
    public static function inputs(array $config,?float $at=null,bool $resolveClock=true): array
    {
        if(!array_key_exists('presetTimers',$config))return [];
        if(!is_array($config['presetTimers'])||!array_is_list($config['presetTimers'])||count($config['presetTimers'])>100)throw new RuntimeException('Ein Preset darf höchstens 100 Folge-Preset-Timer enthalten.',422);
        $at??=microtime(true);$result=[];
        foreach($config['presetTimers'] as $action){
            if(!is_array($action)||!is_array($action['timing'] ?? null))throw new RuntimeException('Ungültiger Folge-Preset-Timer.',422);
            $timing=$action['timing'];$zone=$timing['timezone'] ?? 'Europe/Berlin';
            if(!is_string($zone)||!in_array($zone,DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true))throw new RuntimeException('Ungültige Timer-Zeitzone.',422);
            if(($timing['mode'] ?? '')==='time'){
                $clock=$timing['startTime'] ?? null;
                if(!is_string($clock)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D',$clock))throw new RuntimeException('Die Timer-Uhrzeit muss HH:mm oder HH:mm:ss sein.',422);
                $date=(new DateTimeImmutable('@'.(int)$at))->setTimezone(new DateTimeZone($zone))->format('Y-m-d');
                $start=$resolveClock?ExternalSchedule::localTimestamp($date.'T'.$clock.(strlen($clock)===5?':00':''),$zone):PresetTriggers::stamp($at);
            }elseif(($timing['mode'] ?? '')==='datetime')$start=$timing['startsAt'] ?? null;
            else throw new RuntimeException('Timer-Zeitmodus muss time oder datetime sein.',422);
            // Validate using the same one-shot contract as manually created timers.
            $body=['name'=>$action['name'] ?? 'Folge-Preset','presetId'=>$action['presetId'] ?? null,'startsAt'=>$start,'timezone'=>$zone,'enabled'=>true];
            $validated=PresetTriggers::validate($body);$result[]=$validated;
        }
        return $result;
    }
    public static function assertGraph(array $presets,string $rootId,array $config,?float $at=null): void
    {
        $at??=microtime(true);if(!self::inputs($config,$at,false))return;
        $nodes=array_column($presets,null,'id');$nodes[$rootId]=array_replace($nodes[$rootId] ?? [],['id'=>$rootId,'status'=>'active','config'=>$config]);$visiting=[];$heights=[];
        $visit=function(string $id)use(&$visit,&$visiting,&$heights,$nodes,$at):int{
            if(isset($visiting[$id]))throw new RuntimeException('Folge-Preset-Timer dürfen keine Schleife bilden oder sich selbst aufrufen.',422);
            if(isset($heights[$id]))return $heights[$id];
            if(count($visiting)>=32||count($heights)+count($visiting)>=500)throw new RuntimeException('Die Folge-Preset-Kette ist zu lang (höchstens 32 Ebenen und 500 Presets).',422);
            $node=$nodes[$id] ?? null;if(!$node||($node['status'] ?? '')!=='active'||!empty($node['deletedAt']))throw new RuntimeException('Ein Folge-Preset wurde nicht gefunden oder ist archiviert.',422);
            $visiting[$id]=true;$height=1;
            foreach(self::inputs($node['config'] ?? [],$at,false) as $action)$height=max($height,1+$visit($action['presetId']));
            unset($visiting[$id]);if($height>32)throw new RuntimeException('Die Folge-Preset-Kette darf höchstens 32 Ebenen enthalten.',422);
            return $heights[$id]=$height;
        };
        $visit($rootId);
    }
    public static function queue(array &$state,string $presetId,array $config,?float $at=null): array
    {
        $at??=microtime(true);$inputs=self::inputs($config,$at);if(!$inputs)return [];
        self::assertGraph($state['presets'],$presetId,$config,$at);$nodes=array_column($state['presets'],null,'id');$result=[];
        foreach($inputs as $input){
            $name=($nodes[$presetId]['name'] ?? 'Preset').' → '.($input['name']==='Folge-Preset'?$nodes[$input['presetId']]['name']:$input['name']);
            $name=substr($name,0,200);while(!preg_match('//u',$name))$name=substr($name,0,-1);$input['name']=$name;
            $result[]=PresetTriggers::save($state,$input,null,$at,$presetId);
        }
        return $result;
    }
}
