<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;
require_once __DIR__.'/ExternalSchedule.php';

/** CMS counterpart of the standalone advertising generator; works only on the current state snapshot. */
final class AdvertisingService
{
    private const DEFAULTS = ['interstitialSlideIds'=>[],'channelSequences'=>[],'enabled'=>false,'maximumSlides'=>15,'maximumEvents'=>15,'eventTypes'=>[],'eventTypeDefaultMedia'=>[],
        'durationSeconds'=>12,'showDate'=>false,'showTime'=>true,'showSeconds'=>false,'hour12'=>false,
        'channelName'=>'Werbung','locationIds'=>[],'templateLandscapeId'=>null,'templatePortraitId'=>null];

    public static function settings(array $state): array
    {
        $settings = array_replace(self::DEFAULTS, $state['advertisingSettings'] ?? []);
        foreach (['eventTypes','locationIds'] as $key) $settings[$key] = self::strings($settings[$key], $key === 'eventTypes' ? 100 : 500);
        $settings['maximumSlides'] = max(1,min(500,(int)($state['advertisingSettings']['maximumSlides'] ?? $state['advertisingSettings']['maximumEvents'] ?? 15)));
        $settings['maximumEvents'] = max(1,min(200,(int)$settings['maximumEvents']));
        $settings['durationSeconds'] = max(3,min(600,(int)$settings['durationSeconds']));
        $settings['channelName'] = function_exists('mb_substr') ? mb_substr(trim((string)$settings['channelName']),0,200) : substr(trim((string)$settings['channelName']),0,200);
        $settings['channelName'] = $settings['channelName'] ?: 'Werbung';
        return $settings;
    }

    public static function saveSettings(array &$state, array $input): void
    {
        if(isset($input['maximumSlides']) && (filter_var($input['maximumSlides'],FILTER_VALIDATE_INT)===false || (int)$input['maximumSlides']<1 || (int)$input['maximumSlides']>500))throw new RuntimeException('Slides je Werbekanal müssen zwischen 1 und 500 liegen.',422);
        $previous=self::settings($state);
        $legacyChanged=!empty($input['templateId'])&&$input['templateId']!==($previous['templateId'] ?? null)&&!array_key_exists('templateLandscapeId',$input)&&!array_key_exists('templatePortraitId',$input);
        foreach(['templateId','templateLandscapeId','templatePortraitId'] as $key)if(!array_key_exists($key,$input))$input[$key]=$previous[$key] ?? null;
        if($legacyChanged)$input['templateLandscapeId']=$input['templatePortraitId']=$input['templateId'];
        $settings = self::settings(['advertisingSettings'=>array_intersect_key($input,self::DEFAULTS + ['templateId'=>null])]);
        foreach (['enabled','showDate','showTime','showSeconds','hour12'] as $key) {
            if (isset($input[$key]) && !is_bool($input[$key])) throw new RuntimeException('Ungültige Werbeeinstellung: '.$key,422);
        }
        if ((empty($settings['templateLandscapeId'])||empty($settings['templatePortraitId'])) && !empty($settings['templateId'])) {
            $template = self::find($state['templates'], (string)$settings['templateId']);
            if (!$template || ($template['status'] ?? '') === 'archived' || !empty($template['deletedAt'])) throw new RuntimeException('Die ausgewählte Werbevorlage wurde nicht gefunden.',422);
        }
        $media = [];
        if (!is_array($settings['eventTypeDefaultMedia'])) throw new RuntimeException('Ungültige Standardbilder.',422);
        foreach (array_slice($settings['eventTypeDefaultMedia'],0,100,true) as $type=>$id) {
            if (!is_string($id) || !trim((string)$type) || !$id) continue;
            $asset = self::find($state['mediaAssets'],$id);
            if (!$asset || ($asset['type'] ?? '') !== 'image' || !empty($asset['deletedAt'])) throw new RuntimeException('Mindestens ein Standardbild für eine Veranstaltungsart wurde nicht gefunden.',422);
            $media[trim((string)$type)] = $id;
        }
        foreach ($settings['locationIds'] as $id) if (!self::find($state['locations'],$id)) throw new RuntimeException('Ein ausgewählter Standort wurde nicht gefunden.',422);
        $settings['eventTypeDefaultMedia'] = $media;
        foreach(['templateLandscapeId','templatePortraitId'] as $field)if(!empty($settings[$field])){ $t=self::find($state['templates'],(string)$settings[$field]);if(!$t||!empty($t['deletedAt'])||($t['status'] ?? '')==='archived')throw new RuntimeException('Die ausgewählte Werbevorlage wurde nicht gefunden.',422); }
        $settings['channelSequences']=$previous['channelSequences'];
        $settings['interstitialSlideIds']=$input['interstitialSlideIds'] ?? $previous['interstitialSlideIds'];
        if(!is_array($settings['interstitialSlideIds']))throw new RuntimeException('Ungültige Zwischenslides.',422);
        foreach(['landscape','portrait'] as $orientation){
            $ids=$settings['interstitialSlideIds'][$orientation] ?? [];
            if(!is_array($ids)||count($ids)>10)throw new RuntimeException('Höchstens zehn Zwischenslides je Format.',422);
            foreach($ids as $id){$slide=is_string($id)?self::find($state['slides'],$id):null;if(!$slide||!empty($slide['deletedAt'])||!empty($slide['archivedAt']))throw new RuntimeException('Eine ausgewählte Zwischenslide wurde nicht gefunden.',422);}
        }
        $state['advertisingSettings'] = $settings;
        if($settings['enabled']&&!$previous['enabled']){foreach($state['channels'] as &$channel)if(in_array($channel['automationKey'] ?? '',['advertising:channel','advertising:channel:portrait'],true))$channel['status']='published';unset($channel);}
    }

    public static function status(array $state): array
    {
        $settings = self::settings($state);
        $types = self::strings(array_map(static fn(array $event): string => (string)($event['eventType'] ?? ''),$state['events']),10000);
        sort($types,SORT_NATURAL | SORT_FLAG_CASE);
        $playlists=['landscape'=>self::playlist($state,'landscape'),'portrait'=>self::playlist($state,'portrait')];
        $status = ['settings'=>$settings,'availableEventTypes'=>$types,'eligibleEvents'=>count(self::eligible($state,$settings)),
            'generatedSlides'=>max(array_map(static fn(array $items):int=>count(array_filter($items,static fn(array $i):bool=>!empty($i['slide']['virtual']))),$playlists))];
        $status['channels'] = [];
        foreach ($state['channels'] as $channel) if (in_array($channel['automationKey'] ?? '',['advertising:channel','advertising:channel:portrait'],true)) $status['channels'][]=['id'=>$channel['id'],'name'=>$channel['name'],'itemCount'=>count($playlists[$channel['orientation']] ?? []),'orientation'=>$channel['orientation']];
        $status['taggedSlides']=count(array_filter($state['slides'],static fn(array $slide): bool => empty($slide['deletedAt']) && !str_starts_with($slide['automationKey'] ?? '', 'advertising:event:') && self::formats($slide)));
        foreach ($state['channels'] as $channel) if (($channel['automationKey'] ?? '') === 'advertising:channel') {
            $status['channel'] = ['id'=>$channel['id'],'name'=>$channel['name'],'itemCount'=>count($playlists['landscape'])]; break;
        }
        if (isset($state['advertisingLastSynchronizedAt'])) $status['lastSynchronizedAt'] = $state['advertisingLastSynchronizedAt'];
        return $status;
    }

    /** Callbacks use Api's regular persistence helpers, including slide version creation. */
    public static function synchronize(array &$state, callable $saveSlide, callable $saveChannel, ?callable $saveTemplate = null): array
    {
        $settings = self::settings($state);
        if($settings['enabled']&&$saveTemplate){
            $legacy=!empty($settings['templateId'])?self::find($state['templates'],$settings['templateId']):null;
            foreach(['landscape','portrait'] as $orientation){
                $field=$orientation==='landscape'?'templateLandscapeId':'templatePortraitId';
                $template=!empty($settings[$field])?self::find($state['templates'],$settings[$field]):null;
                if($template&&empty($template['deletedAt'])&&($template['status'] ?? '')!=='archived')continue;
                $document=$legacy['document'] ?? self::template();
                $document['elements']=$document['responsiveLayouts'][$orientation] ?? $document['elements'];
                $document['responsiveLayouts']=[$orientation=>$document['elements']];$document['designOrientation']=$orientation;$document['advertisingTemplate']=$orientation;
                $created=$saveTemplate(['name'=>'Werbung · '.($orientation==='landscape'?'Querformat':'Hochkant'),'description'=>'Bearbeitbare Vorlage für automatische Veranstaltungswerbung im Kanal.','orientation'=>$orientation,'status'=>'active','document'=>$document],null);
                $settings[$field]=$created['id'];
            }
            $state['advertisingSettings']=$settings;
        }
        // Keep legacy rows for existing references. New event advertising is never stored as slides.
        foreach ($state['slides'] as &$slide) if (empty($slide['deletedAt']) && self::formats($slide) && empty($slide['endsAt'])) $slide['endsAt'] = gmdate('c',max(time(),strtotime((string)($slide['startsAt'] ?? '')) ?: 0)+7*86400);
        unset($slide);
        foreach (['landscape','portrait'] as $orientation) {
            $key=$orientation==='landscape'?'advertising:channel':'advertising:channel:portrait';$channel=null;
            foreach($state['channels'] as $index=>$item)if(($item['automationKey'] ?? '')===$key){$channel=$item;$channelIndex=$index;break;}
            if($settings['enabled']){
                $items=array_values(array_filter(self::playlist($state,$orientation),static fn(array $item):bool=>empty($item['slide'])&&empty($item['advertisingInterstitial'])));
                $body=['name'=>$settings['channelName'].($orientation==='landscape'?' quer':' Hochkant'),'description'=>'Automatisch: Werbe-Tags und virtuelle Veranstaltungswerbung','status'=>'published','orientation'=>$orientation,'defaultTransition'=>'fade','repeatEnabled'=>true,'items'=>$items,'totalDurationSeconds'=>count($items)*$settings['durationSeconds'],'tags'=>['Werbung','Automatisch',$orientation==='landscape'?'Werbung quer':'Werbung Hochkant'],'locationIds'=>$settings['locationIds'],'automationKey'=>$key,'deletedAt'=>null,'archivedAt'=>null];
                if(!$channel)$saveChannel($body,null);else{$body['items']=$items;$body['status']=$channel['status'];$body['description']=$channel['description'] ?? $body['description'];$body['tags']=$channel['tags'] ?? $body['tags'];$body['defaultTransition']=$channel['defaultTransition'];$body['repeatEnabled']=$channel['repeatEnabled'];$state['channels'][$channelIndex]=array_replace($channel,$body);}
            }elseif($channel)$state['channels'][$channelIndex]['status']='paused';
        }
        $state['advertisingLastSynchronizedAt'] = gmdate('c');
        return self::status($state);
    }

    public static function playlist(array $state,string $orientation): array
    {
        $settings=self::settings($state);if(!$settings['enabled'])return [];
        $manual=array_values(array_filter($state['slides'],static fn(array $s):bool=>!str_starts_with($s['automationKey'] ?? '','advertising:event:')&&empty($s['deletedAt'])&&empty($s['archivedAt'])&&($s['status'] ?? '')==='published'&&in_array($orientation,self::formats($s),true)&&(empty($s['startsAt'])||strtotime($s['startsAt'])<=time())&&(empty($s['endsAt'])||strtotime($s['endsAt'])>time())));
        usort($manual,static fn(array $a,array $b):int=>[$a['endsAt'] ?? '',$a['id']]<=>[$b['endsAt'] ?? '',$b['id']]);
        $events=self::virtualSlides($state,$orientation);$byId=array_column(array_merge($manual,$events),null,'id');
        $active=static fn(array $s):bool=>empty($s['deletedAt'])&&empty($s['archivedAt'])&&($s['status'] ?? '')==='published'&&(empty($s['startsAt'])||strtotime($s['startsAt'])<=time())&&(empty($s['endsAt'])||strtotime($s['endsAt'])>time());
        $selected=[];$seen=[];
        foreach($settings['channelSequences'][$orientation] ?? [] as $item){
            $slide=$byId[$item['slideId']] ?? (!str_starts_with($item['slideId'],'advertising-event:')?self::find($state['slides'],$item['slideId']):null);
            if($slide&&$active($slide)){$selected[]=$item+(!empty($slide['virtual'])?['slide'=>$slide]:[]);$seen[$slide['id']]=true;}
        }
        foreach(array_merge(array_slice($manual,0,$settings['maximumSlides']),$events) as $slide)if(!isset($seen[$slide['id']])){$selected[]=['slideId'=>$slide['id'],'durationSeconds'=>$settings['durationSeconds'],'transition'=>'inherit']+(!empty($slide['virtual'])?['slide'=>$slide]:[]);$seen[$slide['id']]=true;}
        $extras=[];foreach($settings['interstitialSlideIds'][$orientation] ?? [] as $id){$slide=self::find($state['slides'],$id);if($slide&&$active($slide))$extras[]=$slide;}
        $result=[];$index=0;foreach($selected as $item){$result[]=$item;if(!empty($item['slide']['virtual'])&&++$index<count($events))foreach($extras as $slide)$result[]=['slideId'=>$slide['id'],'durationSeconds'=>$settings['durationSeconds'],'transition'=>'inherit','advertisingInterstitial'=>true];}
        return $result;
    }

    public static function virtualSlides(array $state,string $orientation,?int $limit=null): array
    {
        $settings=self::settings($state);if(!$settings['enabled'])return [];
        $id=$settings[$orientation==='landscape'?'templateLandscapeId':'templatePortraitId'] ?? $settings['templateId'] ?? null;
        $template=$id?self::find($state['templates'],$id):null;
        $base=$template&&empty($template['deletedAt'])&&($template['status'] ?? '')!=='archived'?$template['document']:self::template();
        $assets=array_column($state['mediaAssets'],null,'id');$fallbacks=[];
        foreach($settings['eventTypeDefaultMedia'] as $type=>$assetId)if(isset($assets[$assetId])&&empty($assets[$assetId]['deletedAt']))$fallbacks[self::normalizeType($type)]=$assets[$assetId];
        $result=[];
        foreach(array_slice(self::eligible($state,$settings),0,min($settings['maximumEvents'],$limit ?? $settings['maximumEvents'])) as $event){
            $document=$base;$fallback=$fallbacks[self::normalizeType($event['eventType'] ?? '')] ?? null;
            $elements=self::elements($document['responsiveLayouts'][$orientation] ?? $document['elements'] ?? [],$settings,$fallback,!empty($event['imageUrl']));
            $document=array_replace($document,['elements'=>$elements,'responsiveLayouts'=>[$orientation=>$elements],'designOrientation'=>$orientation,'orientation'=>$orientation,'type'=>'event','width'=>$orientation==='portrait'?1080:1920,'height'=>$orientation==='portrait'?1920:1080]);
            $document['advertisingConfigHash']=hash('sha256',json_encode($document,JSON_THROW_ON_ERROR));
            $date=self::overviewDay($event['date'] ?? '');$boundary=self::overviewInstant($event['eventEnd'] ?? '',$date) ?? self::overviewInstant($event['eventStart'] ?? '',$date);
            $result[]=['id'=>'advertising-event:'.$orientation.':'.$event['id'],'name'=>'Werbung · '.$event['title'],'eventId'=>$event['id'],'orientation'=>$orientation,'type'=>'event','document'=>$document,'status'=>'published','endsAt'=>$boundary!==null?gmdate('c',$boundary):null,'tags'=>['Werbung','Veranstaltung',$orientation==='portrait'?'Werbung Hochkant':'Werbung quer'],'locationIds'=>$settings['locationIds'],'virtual'=>true,'automationKey'=>'advertising:event:'.$event['id'],'createdAt'=>$event['createdAt'] ?? '', 'updatedAt'=>$event['updatedAt'] ?? ''];
        }
        return $result;
    }

    public static function channel(array $state,array $channel): array
    {
        if(!in_array($channel['automationKey'] ?? '',['advertising:channel','advertising:channel:portrait'],true)||!self::settings($state)['enabled'])return $channel;
        $channel['items']=self::playlist($state,($channel['automationKey'] ?? '')==='advertising:channel:portrait'?'portrait':'landscape');
        $channel['totalDurationSeconds']=array_sum(array_column($channel['items'],'durationSeconds'));return $channel;
    }

    public static function overviewCount(mixed $value):int
    {
        return is_numeric($value)&&is_finite((float)$value)&&(float)$value>=1?min(50,(int)$value):6;
    }

    private static function overviewDay(mixed $value):string
    {
        $text=trim((string)($value ?? ''));
        $digits=preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/',$text,$m)?$m[3].$m[2].$m[1]:substr(preg_replace('/\D/','',$text),0,8);
        return strlen($digits)===8&&checkdate((int)substr($digits,4,2),(int)substr($digits,6,2),(int)substr($digits,0,4))?$digits:'';
    }

    private static function overviewInstant(mixed $value,string $date):?int
    {
        $text=trim((string)($value ?? ''));if($text==='')return null;
        $clock=preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$text)===1;
        if($clock&&$date==='')return null;
        if($clock)$text=substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2).'T'.$text;
        try{if(preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d(?::\d\d)?$/',$text)){if(strlen($text)===16)$text.=':00';return strtotime(ExternalSchedule::localTimestamp($text,'Europe/Berlin'));}$time=new \DateTimeImmutable($text,new \DateTimeZone('Europe/Berlin'));$errors=\DateTimeImmutable::getLastErrors();return $errors&&($errors['warning_count']||$errors['error_count'])?null:$time->getTimestamp();}catch(\Throwable){return null;}
    }

    /** One lazy, bounded selection per playback request, reused by every overview on it. */
    public static function overviewProjector(array $events,?int $now=null):callable
    {
        $now??=time();$selected=null;
        $select=static function()use($events,$now):array{
            $today=(new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Ymd');$rows=[];
            foreach($events as $event){
                if(!empty($event['deletedAt'])||!empty($event['archivedAt'])||in_array($event['status'] ?? '',['cancelled','archived','ended'],true))continue;
                $date=self::overviewDay($event['date'] ?? '');$start=self::overviewInstant($event['eventStart'] ?? '',$date);$end=self::overviewInstant($event['eventEnd'] ?? '',$date);$boundary=$end ?? $start;
                if($boundary!==null?$boundary<$now:($date===''||$date<$today))continue;
                $order=$start ?? ($date!==''?strtotime(substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2).'T00:00:00Z'):false);if($order===false)continue;
                $rows[]=['event'=>$event,'order'=>$order];
            }
            usort($rows,static fn(array $a,array $b):int=>$a['order']<=>$b['order'] ?: strcmp((string)$a['event']['id'],(string)$b['event']['id']));
            return array_map(static function(array $row):array{
                $event=$row['event'];$out=array_intersect_key($event,array_flip(['id','title','subtitle','date','eventStart','admissionStart','imageUrl','ticketUrl','remainingTickets','status']));$date=self::overviewDay($event['date'] ?? '');if($date!=='')$out['date']=$date;
                foreach(['eventStart','admissionStart'] as $key){$time=self::overviewInstant($event[$key] ?? '',$date);if($time!==null)$out[$key]=gmdate('Y-m-d\TH:i:s\Z',$time);}return $out;
            },array_slice($rows,0,50));
        };
        $visit=static function($value)use(&$visit,&$selected,$select){
            if(!is_array($value))return $value;
            if(($value['type'] ?? '')==='event-overview'){$selected??=$select();$value['eventCount']=self::overviewCount($value['eventCount'] ?? 6);$value['events']=array_slice($selected,0,$value['eventCount']);return $value;}
            foreach($value as $key=>$item)$value[$key]=$visit($item);return $value;
        };
        return $visit;
    }

    public static function overviewSlide(array $slide,array $events):array{return self::overviewProjector($events)($slide);}

    public static function stripOverviewRuntime(mixed $value):mixed
    {
        if(!is_array($value))return $value;
        if(($value['type'] ?? '')==='event-overview')unset($value['events']);
        foreach($value as $key=>$item)$value[$key]=self::stripOverviewRuntime($item);return $value;
    }

    public static function formats(array $slide): array
    {
        $tags=array_map([self::class,'normalizeType'],$slide['tags'] ?? []);
        $formats=[];
        if(in_array('werbung quer',$tags,true))$formats[]='landscape';
        if(in_array('werbung hochkant',$tags,true))$formats[]='portrait';
        if($formats)return $formats;
        $orientation=$slide['orientation'] ?? 'landscape';
        return in_array('werbung',$tags,true)?($orientation==='auto'?['landscape','portrait']:[$orientation]):[];
    }

    private static function elements(array $elements,array $settings,?array $fallback,bool $hasImage): array
    {
        $dynamic = false;
        foreach ($elements as &$element) {
            if (($element['type'] ?? '') === 'image' && empty($element['src'])) {
                $dynamic = true; $element['src'] = $hasImage ? '' : ($fallback['src'] ?? '');
                unset($element['mediaAssetId']);
                if (!$hasImage && $fallback) $element['mediaAssetId'] = $fallback['id'];
            }
            if (in_array($element['type'] ?? '',['event','event-field'],true)) foreach (['showDate','showTime','showSeconds','hour12'] as $field) $element[$field] = $settings[$field];
        }
        unset($element);
        if (!$dynamic) array_unshift($elements,array_replace(self::template()['elements'][0],['src'=>$hasImage ? '' : ($fallback['src'] ?? '')],!$hasImage && $fallback ? ['mediaAssetId'=>$fallback['id']] : []));
        return $elements;
    }

    private static function eligible(array $state,array $settings): array
    {
        $types = array_flip(array_map([self::class,'normalizeType'],$settings['eventTypes']));
        $today = (new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin')))->format('Ymd');
        $events = array_values(array_filter($state['events'],static function(array $event) use ($types,$today): bool {
            if (!empty($event['deletedAt']) || !empty($event['archivedAt']) || in_array($event['status'] ?? '',['cancelled','archived'],true) || (!empty($event['sourceId'])&&!isset($types[self::normalizeType((string)($event['eventType'] ?? ''))]))) return false;
            $date=self::overviewDay($event['date'] ?? '');$boundary=self::overviewInstant($event['eventEnd'] ?? '',$date) ?? self::overviewInstant($event['eventStart'] ?? '',$date);
            return $boundary !== null ? $boundary >= time() : $date >= $today;
        }));
        usort($events,static fn(array $a,array $b): int => [($a['eventStart'] ?? $a['date'] ?? ''),$a['title']] <=> [($b['eventStart'] ?? $b['date'] ?? ''),$b['title']]);
        return $events;
    }

    private static function template(): array
    {
        $document = ['advertisingTemplate'=>true,'designOrientation'=>'landscape','settings'=>['background'=>['mode'=>'gradient','color1'=>'#17342b','color2'=>'#315b49','direction'=>'135deg'],'logo'=>['src'=>'','position'=>'top-left'],'clock'=>['enabled'=>false,'position'=>'bottom-right']],
            'elements'=>[['id'=>'advertising-image','type'=>'image','x'=>0,'y'=>0,'width'=>100,'height'=>100,'src'=>'','background'=>'#17342b','opacity'=>100,'padding'=>0,'radius'=>0],
                ['id'=>'advertising-event','type'=>'event','x'=>8,'y'=>55,'width'=>84,'height'=>35,'background'=>'rgba(0,0,0,.58)','color'=>'#ffffff','fontSize'=>40.5,'fontSizeUnit'=>'pt','responsiveText'=>true,'autoFontSize'=>true,'align'=>'center','fontWeight'=>700,'padding'=>24,'radius'=>16]]];
        $document['responsiveLayouts']=['landscape'=>$document['elements'],'portrait'=>array_map(static fn(array $element): array => ($element['type'] ?? '')==='event'?array_replace($element,['y'=>58,'height'=>34,'fontSize'=>30]):$element,$document['elements'])];
        return $document;
    }

    private static function normalizeType(string $value): string
    {
        if (class_exists(\Normalizer::class)) $value = \Normalizer::normalize($value,\Normalizer::FORM_KC) ?: $value;
        $value = trim(preg_replace('/\s+/u',' ',$value) ?? $value);
        return function_exists('mb_strtolower') ? mb_strtolower($value,'UTF-8') : strtr(strtolower($value),['Ä'=>'ä','Ö'=>'ö','Ü'=>'ü']);
    }

    private static function strings(mixed $values,int $limit): array
    {
        if (!is_array($values)) throw new RuntimeException('Ungültige Auswahlliste.',422);
        return array_slice(array_values(array_unique(array_filter(array_map(static fn($value): string => is_string($value) ? trim($value) : '',$values),static fn(string $value): bool => $value !== ''))),0,$limit);
    }

    private static function find(array $records,string $id): ?array
    {
        foreach ($records as $record) if ($record['id'] === $id) return $record;
        return null;
    }
}
