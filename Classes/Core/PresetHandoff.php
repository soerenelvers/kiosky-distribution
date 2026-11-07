<?php
declare(strict_types=1);
namespace Kiosky\Core;

/** Per-display retirement of preset runs; warnings and group memberships are retained. */
final class PresetHandoff
{
    public static function source(mixed $source): bool { return $source==='external_preset'||str_starts_with((string)$source,'preset:'); }
    public static function key(string $kind,array $item): string { return $kind.':'.$item['id'].':'.($kind==='assignment'?($item['source'] ?? '').':'.($item['updatedAt'] ?? ''):''); }
    private static function stamp(mixed $value): float { try{return (float)(new \DateTimeImmutable((string)$value))->format('U.u');}catch(\Throwable){return -INF;} }
    private static function context(array $state,array $display): array
    {
        $display['matrixId']=null;
        foreach($state['matrices'] as $m)if(empty($m['deletedAt'])&&($m['status'] ?? 'active')==='active'&&in_array($display['id'],array_column($m['members'] ?? [],'displayId'),true)){$display['matrixId']=$m['id'];break;}
        return $display;
    }
    private static function matches(array $d,array $t): bool
    {
        return ($t['type'] ?? '')==='global'||(($t['type'] ?? '')==='display'&&($t['id'] ?? '')===$d['id'])
            ||(($t['type'] ?? '')==='group'&&in_array($t['id'] ?? '',$d['groupIds'] ?? [],true))
            ||(($t['type'] ?? '')==='matrix'&&($t['id'] ?? '')===($d['matrixId'] ?? null))
            ||(($t['type'] ?? '')==='location'&&($t['id'] ?? '')===($d['locationId'] ?? null));
    }
    private static function assignments(array $state): array
    {
        if(empty($state['assignments']))return [];
        $legacy=[];foreach(array_reverse($state['presetExecutions']) as $run)foreach($run['result']['assignments'] ?? [] as $a)if(!isset($legacy[$a['id']]))$legacy[$a['id']]=$a;
        return array_map(static function(array $a)use($legacy):array{
            $old=$legacy[$a['id']] ?? null;
            $a['preset']=self::source($a['source'] ?? '')||($old&&($old['source'] ?? null)===($a['source'] ?? null)&&($old['updatedAt'] ?? '')===($a['updatedAt'] ?? '')&&($old['content'] ?? null)===($a['content'] ?? null));return $a;
        },$state['assignments']);
    }
    private static function candidates(array $state,array $display,float $at): array
    {
        $result=[];
        foreach(self::assignments($state) as $a)if($a['preset']&&self::matches($display,$a['target'])&&(empty($a['startsAt'])||self::stamp($a['startsAt'])<=$at)&&(empty($a['expiresAt'])||self::stamp($a['expiresAt'])>$at))$result[]=['key'=>self::key('assignment',$a),'activation'=>max(self::stamp($a['updatedAt']),empty($a['startsAt'])?0:self::stamp($a['startsAt']))];
        foreach($state['warnings'] as $w){
            if(!self::source($w['source'] ?? '')||($w['status'] ?? '')!=='active'||(!empty($w['startsAt'])&&self::stamp($w['startsAt'])>$at)||(!empty($w['endsAt'])&&self::stamp($w['endsAt'])<=$at))continue;
            foreach($w['targets'] ?? [] as $target){$parts=explode(':',$target,2);$t=count($parts)===2?['type'=>$parts[0],'id'=>$parts[1]]:['type'=>in_array($target,['global','all'],true)?'global':'group','id'=>$target];if(self::matches($display,$t)){$result[]=['key'=>self::key('warning',$w),'activation'=>max(self::stamp($w['publishedAt'] ?? $w['updatedAt']),empty($w['startsAt'])?0:self::stamp($w['startsAt']))];break;}}
        }
        return $result;
    }
    public static function releaseTargets(array &$state,array $targets): void
    {
        foreach($state['displays'] as $display){if(!empty($display['deletedAt']))continue;$d=self::context($state,$display);
            foreach($targets as $t)if(self::matches($d,$t)){foreach(self::candidates($state,$d,microtime(true)) as $p)$state['presetHandoffs'][$d['id']][$p['key']]=true;break;}
        }
    }
    public static function released(array &$state,array $display,bool $scheduled=true): array
    {
        $d=self::context($state,$display);$at=microtime(true);$result=$state['presetHandoffs'][$d['id']] ?? [];
        $presets=array_values(array_filter(self::candidates($state,$d,$at),static fn(array $p):bool=>empty($result[$p['key']])));
        if(!$presets)return $result;
        $latest=-INF;
        foreach($scheduled?$state['schedules']:[] as $s)if(($s['status'] ?? '')==='published'&&empty($s['deletedAt'])&&self::matches($d,['type'=>$s['targetType'] ?? '', 'id'=>$s['targetId'] ?? ''])){
            $latest=max($latest,ExternalSchedule::latestStart($s,$at) ?? -INF,ExternalSchedule::active($s,gmdate('c'))?self::stamp($s['updatedAt'] ?? ''):-INF);
        }
        foreach(self::assignments($state) as $a)if(!$a['preset']&&self::matches($d,$a['target'])&&(empty($a['startsAt'])||self::stamp($a['startsAt'])<=$at))$latest=max($latest,self::stamp($a['updatedAt']),empty($a['startsAt'])?0:self::stamp($a['startsAt']));
        foreach($presets as $p)if($latest>$p['activation'])$result[$p['key']]=true;
        if($result)$state['presetHandoffs'][$d['id']]=$result;
        return $result;
    }
}
