<?php

declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;

final class MenuSubscriptions
{
    public function __construct(private readonly mixed $download=null,private readonly mixed $render=null) {}
    private static function text(mixed $value,int $max): string
    {
        if(!is_string($value) || trim($value)==='' || mb_strlen($value)>$max || preg_match('/[\x00-\x1f\x7f]/',$value))throw new RuntimeException('Name oder Tag ist ungültig.',422);
        return trim($value);
    }
    public static function input(array $body): array
    {
        if(array_diff(array_keys($body),['url','name','slideName','tag','intervalMinutes','enabled','revision','names']))throw new RuntimeException('Ungültige Abonnementfelder.',422);
        if(!is_int($body['intervalMinutes'] ?? null) || $body['intervalMinutes']<1 || $body['intervalMinutes']>10080 || !is_bool($body['enabled'] ?? null))throw new RuntimeException('Abgleichintervall muss zwischen 1 und 10080 Minuten liegen.',422);
        return ['url'=>MenuWebPdf::url($body['url'] ?? null),'name'=>self::text($body['name'] ?? null,100),'slideName'=>self::text($body['slideName'] ?? null,80),'tag'=>self::text($body['tag'] ?? null,80),'intervalMinutes'=>$body['intervalMinutes'],'enabled'=>$body['enabled']];
    }
    private static function find(array $items,string $id): ?array
    {
        foreach($items as $item)if(($item['id'] ?? '')===$id && empty($item['deletedAt']) && ($item['status'] ?? '')!=='archived')return $item;
        return null;
    }
    private static function expose(array $state,array $record): array
    {
        $record['pages']=array_map(static function(string $id) use ($state): array {$slide=self::find($state['slides'],$id);return ['id'=>$id,'name'=>$slide['name'] ?? 'Slide fehlt','tags'=>$slide['tags'] ?? []];},$record['slideIds']);return $record;
    }
    public function handle(array &$state,string $method,string $path,array $body,callable $saveSlide,callable $saveChannel): array
    {
        if(!preg_match('#^/api/menus/subscriptions(?:/([^/]+)(/check)?)?$#',$path,$match))throw new RuntimeException('Abonnement nicht gefunden.',404);
        $state['menuSubscriptions'] ??= [];
        if($method==='GET' && empty($match[1]))return [200,['data'=>array_values(array_map(static fn(array $r): array=>self::expose($state,$r),$state['menuSubscriptions'])),'rendererAvailable'=>MenuWebPdf::available()],false];
        $id=$match[1] ?? null;$current=$id?($state['menuSubscriptions'][$id] ?? null):null;
        if($id && !$current)throw new RuntimeException('Abonnement nicht gefunden.',404);
        if($method==='DELETE' && $current && empty($match[2])){
            if(($body['revision'] ?? null)!==$current['revision'])throw new RuntimeException('Abonnement wurde geändert. Bitte neu laden.',409);
            unset($state['menuSubscriptions'][$id]);return [200,['ok'=>true],true];
        }
        if($method==='POST' && $current && !empty($match[2])){$updated=$this->synchronize($state,$current,$saveSlide,$saveChannel);return [200,['subscription'=>self::expose($state,$updated)],true];}
        if(($method==='POST' && !$current) || ($method==='PUT' && $current && empty($match[2]))){
            $fields=self::input($body);
            if(!$current){
                if(count($state['menuSubscriptions'])>=20)throw new RuntimeException('Höchstens 20 PDF-Abonnements pro Installation.',422);
                if(isset($body['names']) && (!is_array($body['names']) || !array_is_list($body['names']) || count($body['names'])>20))throw new RuntimeException('Ungültige Seitennamen.',422);
                $names=isset($body['names'])?array_map(static fn($v): string=>self::text($v,100),$body['names']):null;
                $record=$fields+['id'=>bin2hex(random_bytes(16)),'slideIds'=>[],'revision'=>1];
                $updated=$this->synchronize($state,$record,$saveSlide,$saveChannel,true,$names);return [201,['subscription'=>self::expose($state,$updated)],true];
            }
            if(($body['revision'] ?? null)!==$current['revision'])throw new RuntimeException('Abonnement wurde geändert. Bitte neu laden.',409);
            if($fields['url']!==$current['url'])throw new RuntimeException('Für eine andere PDF-Adresse bitte ein neues Abonnement anlegen.',422);
            if(isset($body['names']) && (!is_array($body['names']) || count($body['names'])!==count($current['slideIds']) || !array_is_list($body['names'])))throw new RuntimeException('Ungültige Seitennamen.',422);
            $names=isset($body['names'])?array_map(static fn($v): string=>self::text($v,100),$body['names']):null;
            $staged=$state;
            foreach($current['slideIds'] as $index=>$slideId){
                $slide=self::find($staged['slides'],$slideId);if(!$slide)throw new RuntimeException('Eine abonnierte Slide fehlt. Bitte wiederherstellen.',422);
                $name=$names[$index] ?? $slide['name'];$tags=$slide['tags'] ?? [];
                if($fields['tag']!==$current['tag'])$tags=array_values(array_unique([...array_filter($tags,static fn(string $tag): bool=>$tag!==$current['tag']),$fields['tag']]));
                if($name!==$slide['name'] || $tags!==($slide['tags'] ?? []))$saveSlide($staged,array_replace($slide,['name'=>$name,'tags'=>$tags]),$slideId);
            }
            $channel=isset($current['channelId'])?self::find($staged['channels'],$current['channelId']):null;
            if($channel && ($fields['name']!==$current['name'] || $fields['tag']!==$current['tag']))$saveChannel($staged,array_replace($channel,['name'=>$fields['name']!==$current['name']?$fields['name']:$channel['name'],'tags'=>$fields['tag']!==$current['tag']?array_values(array_unique([...array_filter($channel['tags'] ?? [],static fn(string $tag): bool=>$tag!==$current['tag']),$fields['tag']])):($channel['tags'] ?? [])]),$channel['id']);
            $record=array_replace($current,$fields,['revision'=>$current['revision']+1,'nextCheckAt'=>gmdate('c',time()+$fields['intervalMinutes']*60)]);
            $staged['menuSubscriptions'][$id]=$record;$state=$staged;return [200,['subscription'=>self::expose($state,$record)],true];
        }
        throw new RuntimeException('Methode nicht erlaubt.',405);
    }
    private function synchronize(array &$state,array $record,callable $saveSlide,callable $saveChannel,bool $creating=false,?array $initialNames=null): array
    {
        $checked=gmdate('c');$staged=$state;
        try{
            $data=$this->download?($this->download)($record['url']):MenuWebPdf::download($record['url']);$hash=hash('sha256',$data);
            $pages=$hash!==($record['hash'] ?? '')?($this->render?($this->render)($data):MenuWebPdf::render($data)):null;
            if($pages!==null && (count($pages)<1 || count($pages)>20))throw new RuntimeException('Ungültige PDF-Seitenzahl.',422);
            if($initialNames!==null && count($initialNames)!==count($pages ?? []))throw new RuntimeException('Bitte für jede PDF-Seite genau einen Namen angeben oder die Liste leer lassen.',422);
            $channel=isset($record['channelId'])?self::find($staged['channels'],$record['channelId']):null;
            if(isset($record['channelId']) && !$channel)throw new RuntimeException('Abonnierter Kanal fehlt oder liegt im Papierkorb.',422);
            foreach($record['slideIds'] as $id)if(!self::find($staged['slides'],$id))throw new RuntimeException('Abonnierte Slide fehlt oder liegt im Papierkorb.',422);
            $updated=array_replace($record,['lastCheckedAt'=>$checked,'nextCheckAt'=>gmdate('c',time()+$record['intervalMinutes']*60),'error'=>'','revision'=>$record['revision']+1]);
            if($pages!==null){
                $slides=[];$items=[];
                foreach($pages as $index=>$page){
                    $id=$record['slideIds'][$index] ?? null;$existing=$id?self::find($staged['slides'],$id):null;
                    $document=MenuWebPdf::document($page,$record['slideName'].'.pdf',$index+1,count($pages),$existing['document'] ?? null);
                    $slides[]=$slide=$saveSlide($staged,$existing?array_replace($existing,['document'=>$document]):['name'=>$initialNames[$index] ?? ($record['slideName'].' · '.($index+1)),'tags'=>['Speisekarte','PDF',$record['tag']],'orientation'=>$document['designOrientation'],'type'=>'custom','status'=>'published','document'=>$document],$id);
                    $oldItem=null;foreach($channel['items'] ?? [] as $item)if($item['slideId']===$slide['id']){$oldItem=$item;break;}
                    $items[]=$oldItem ?? ['slideId'=>$slide['id'],'durationSeconds'=>12,'transition'=>'inherit'];
                }
                $saved=$saveChannel($staged,$channel?array_replace($channel,['items'=>$items]):['name'=>$record['name'],'orientation'=>'auto','status'=>'draft','defaultTransition'=>'fade','repeatEnabled'=>true,'items'=>$items,'tags'=>['Speisekarte',$record['tag']]],$record['channelId'] ?? null);
                foreach(array_slice($record['slideIds'],count($pages)) as $removedId){$slide=self::find($staged['slides'],$removedId);$saveSlide($staged,array_replace($slide,['status'=>'draft']),$removedId);}
                $updated=array_replace($updated,['hash'=>$hash,'slideIds'=>array_column($slides,'id'),'channelId'=>$saved['id'],'lastChangedAt'=>$checked]);
            }
            $staged['menuSubscriptions'][$record['id']]=$updated;$state=$staged;return $updated;
        }catch(\Throwable $error){
            if($creating)throw $error;
            $updated=array_replace($record,['lastCheckedAt'=>$checked,'nextCheckAt'=>gmdate('c',time()+$record['intervalMinutes']*60),'error'=>'PDF-Abgleich fehlgeschlagen. Bitte HTTPS-Adresse, PDF und Servervoraussetzungen prüfen. Bisherige Inhalte bleiben erhalten.','revision'=>$record['revision']+1]);
            $state['menuSubscriptions'][$record['id']]=$updated;return $updated;
        }
    }
    public function runDue(array &$state,callable $saveSlide,callable $saveChannel): int
    {
        if(($state['featureSettings']['menus'] ?? true)===false)return 0;$ran=0;
        foreach($state['menuSubscriptions'] ?? [] as $record)if($record['enabled'] && strtotime($record['nextCheckAt'] ?? '')<=time()){$this->synchronize($state,$record,$saveSlide,$saveChannel);$ran++;}
        return $ran;
    }
}
