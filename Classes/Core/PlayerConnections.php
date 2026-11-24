<?php
declare(strict_types=1);
namespace Kiosky\Core;

/** A pairing becomes a separate, display-bound bearer credential. Never expose hashes in listings. */
final class PlayerConnections
{
    public static function authenticate(array $state,array $display,string $key):?string
    {
        if(strlen($key)>512||!preg_match('/^pc\.([a-f0-9-]{32,36})\.([a-f0-9]{48,64})$/D',$key,$m))return null;
        foreach($state['playerPairings']??[] as $p)if(($p['id']??'')===$m[1]&&($p['displayId']??'')===$display['id']&&($p['credentialVersion']??0)===1&&empty($p['revokedAt'])&&($p['status']??'')==='paired'&&hash_equals((string)($p['secretHash']??''),hash('sha256',$m[2]))&&hash_equals((string)($p['displayKeyHash']??''),hash('sha256',(string)$display['playerKey'])))return $m[1];
        return null;
    }
    public static function listing(array $state,array $display):array
    {
        $out=[];foreach($state['playerPairings']??[] as $p){if(($p['displayId']??'')!==$display['id']||($p['credentialVersion']??0)!==1||!empty($p['revokedAt'])||($p['status']??'')!=='paired'||!hash_equals((string)($p['displayKeyHash']??''),hash('sha256',(string)$display['playerKey'])))continue;
            $seen=$display['playerConnections'][$p['id']]['lastSeenAt']??null;$time=$seen?strtotime($seen):false;
            $out[]=['id'=>$p['id'],'platform'=>$p['platform']??'Player','pairedAt'=>$p['pairedAt']??null,'lastSeenAt'=>$seen,'online'=>$time!==false&&QualityClock::now()>=$time&&QualityClock::now()-$time<=60];
        }return $out;
    }
    public static function register(array &$state,array $display,string $secret,string $platform):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new \RuntimeException('Ungültige Player-Registrierung.',422);
        $hash=hash('sha256',$secret);$id=null;
        foreach($state['playerPairings']??[] as $p)if(($p['displayId']??'')===$display['id']&&($p['secretHash']??'')===$hash&&($p['credentialVersion']??0)===1){if(!empty($p['revokedAt']))throw new \RuntimeException('Player wurde entkoppelt.',410);$id=$p['id'];break;}
        if($id===null){$id=bin2hex(random_bytes(16));$state['playerPairings'][]=['id'=>$id,'status'=>'paired','code'=>'','secretHash'=>$hash,'credentialVersion'=>1,'displayKeyHash'=>hash('sha256',(string)$display['playerKey']),'displayId'=>$display['id'],'platform'=>substr($platform,0,80),'pairedAt'=>gmdate('c',QualityClock::now()),'createdAt'=>gmdate('c',QualityClock::now())];}
        return ['playerKey'=>'pc.'.$id.'.'.$secret];
    }
    public static function targets(array $state,string $displayId):array
    {
        foreach($state['displays'] as $d)if($d['id']===$displayId)return array_column(self::listing($state,$d),'id');return [];
    }
    public static function pending(array $state,string $displayId,?string $playerId):array
    {
        return array_values(array_filter($state['commands']??[],static fn(array $c):bool=>($c['displayId']??'')===$displayId&&($playerId===null?empty($c['legacyAcknowledged']):(in_array($playerId,$c['playerIds']??[],true)&&!in_array($playerId,$c['acknowledgedPlayerIds']??[],true)))&&(!isset($c['createdAt'])||strtotime($c['createdAt'])>QualityClock::now()-86400)));
    }
    public static function acknowledge(array &$state,string $displayId,?string $playerId,array $ids):void
    {
        foreach($state['commands'] as &$c){if(($c['displayId']??'')!==$displayId||!in_array($c['id'],$ids,true))continue;
            if($playerId===null)$c['legacyAcknowledged']=true;elseif(in_array($playerId,$c['playerIds']??[],true))$c['acknowledgedPlayerIds']=array_values(array_unique([...($c['acknowledgedPlayerIds']??[]),$playerId]));
        }unset($c);
        $state['commands']=array_values(array_filter($state['commands'],static fn(array $c):bool=>!empty($c['playerIds'])?count(array_diff($c['playerIds'],$c['acknowledgedPlayerIds']??[]))>0:empty($c['legacyAcknowledged'])));
    }
    public static function mergeObservations(array $current,array $incoming):array
    {
        foreach($incoming as $id=>$value)if(strcmp((string)($value['lastSeenAt']??''),(string)($current[$id]['lastSeenAt']??''))>0)$current[$id]=$value;return $current;
    }
}
