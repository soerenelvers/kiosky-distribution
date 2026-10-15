<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;

/** CMS counterpart of the standalone advertising generator; works only on the current state snapshot. */
final class AdvertisingService
{
    private const DEFAULTS = ['enabled'=>false,'maximumEvents'=>15,'eventTypes'=>[],'eventTypeDefaultMedia'=>[],
        'durationSeconds'=>12,'showDate'=>false,'showTime'=>true,'showSeconds'=>false,'hour12'=>false,
        'channelName'=>'Werbung','locationIds'=>[]];

    public static function settings(array $state): array
    {
        $settings = array_replace(self::DEFAULTS, $state['advertisingSettings'] ?? []);
        foreach (['eventTypes','locationIds'] as $key) $settings[$key] = self::strings($settings[$key], $key === 'eventTypes' ? 100 : 500);
        $settings['maximumEvents'] = max(1,min(200,(int)$settings['maximumEvents']));
        $settings['durationSeconds'] = max(3,min(600,(int)$settings['durationSeconds']));
        $settings['channelName'] = function_exists('mb_substr') ? mb_substr(trim((string)$settings['channelName']),0,200) : substr(trim((string)$settings['channelName']),0,200);
        $settings['channelName'] = $settings['channelName'] ?: 'Werbung';
        return $settings;
    }

    public static function saveSettings(array &$state, array $input): void
    {
        $settings = self::settings(['advertisingSettings'=>array_intersect_key($input,self::DEFAULTS + ['templateId'=>null])]);
        foreach (['enabled','showDate','showTime','showSeconds','hour12'] as $key) {
            if (isset($input[$key]) && !is_bool($input[$key])) throw new RuntimeException('Ungültige Werbeeinstellung: '.$key,422);
        }
        if (!empty($settings['templateId'])) {
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
        $state['advertisingSettings'] = $settings;
    }

    public static function status(array $state): array
    {
        $settings = self::settings($state);
        $types = self::strings(array_map(static fn(array $event): string => (string)($event['eventType'] ?? ''),$state['events']),10000);
        sort($types,SORT_NATURAL | SORT_FLAG_CASE);
        $status = ['settings'=>$settings,'availableEventTypes'=>$types,'eligibleEvents'=>count(self::eligible($state,$settings)),
            'generatedSlides'=>count(array_filter($state['slides'],static fn(array $slide): bool => str_starts_with($slide['automationKey'] ?? '', 'advertising:event:') && ($slide['status'] ?? '') !== 'archived' && empty($slide['deletedAt'])))];
        foreach ($state['channels'] as $channel) if (($channel['automationKey'] ?? '') === 'advertising:channel') {
            $status['channel'] = ['id'=>$channel['id'],'name'=>$channel['name'],'itemCount'=>count($channel['items'] ?? [])]; break;
        }
        if (isset($state['advertisingLastSynchronizedAt'])) $status['lastSynchronizedAt'] = $state['advertisingLastSynchronizedAt'];
        return $status;
    }

    /** Callbacks use Api's regular persistence helpers, including slide version creation. */
    public static function synchronize(array &$state, callable $saveSlide, callable $saveChannel): array
    {
        $settings = self::settings($state);
        $events = $settings['enabled'] ? self::eligible($state,$settings) : [];
        $template = !empty($settings['templateId']) ? self::find($state['templates'],(string)$settings['templateId']) : null;
        if (($template['status'] ?? '') === 'archived' || !empty($template['deletedAt'])) $template = null;
        $base = $template['document'] ?? self::template();
        $existing = [];
        foreach ($state['slides'] as $index=>$slide) if (!empty($slide['automationKey'])) $existing[$slide['automationKey']] = $index;
        $assets = array_column($state['mediaAssets'],null,'id');
        $fallbacks = [];
        foreach ($settings['eventTypeDefaultMedia'] as $type=>$id) if (isset($assets[$id]) && empty($assets[$id]['deletedAt']) && ($assets[$id]['type'] ?? '') === 'image') $fallbacks[self::normalizeType((string)$type)] = $assets[$id];
        $active = []; $slideIds = [];
        foreach ($events as $event) {
            $key = 'advertising:event:'.$event['id']; $active[$key] = true;
            $document = $base;
            $fallback = $fallbacks[self::normalizeType((string)$event['eventType'])] ?? null;
            $hasImage = !empty($event['imageUrl']);
            $document['elements'] = self::elements($document['elements'] ?? [],$settings,$fallback,$hasImage);
            foreach (($document['responsiveLayouts'] ?? []) as $orientation=>$layout) {
                if (is_array($layout)) $document['responsiveLayouts'][$orientation] = self::elements($layout,$settings,$fallback,$hasImage);
            }
            $document['advertisingConfigHash'] = hash('sha256',json_encode([$document,$settings['showDate'],$settings['showTime'],$settings['showSeconds'],$settings['hour12'],$hasImage,$fallback['id'] ?? null,$fallback['updatedAt'] ?? null],JSON_THROW_ON_ERROR));
            $body = ['name'=>'Werbung · '.$event['title'],'eventId'=>$event['id'],'orientation'=>'auto','type'=>'event','document'=>$document,'status'=>'published','endsAt'=>$event['eventEnd'] ?? null,
                'tags'=>['Werbung','Automatisch','Veranstaltung',$event['eventType'],'Veranstaltung:'.($event['externalNumber'] ?? $event['id'])],'automationKey'=>$key,'deletedAt'=>null,'archivedAt'=>null];
            $index = $existing[$key] ?? null;
            $current = $index === null ? null : $state['slides'][$index];
            if (!$current || ($current['document']['advertisingConfigHash'] ?? '') !== $document['advertisingConfigHash']) $saved = $saveSlide($body,$current['id'] ?? null);
            else {
                $saved = array_replace($current,array_diff_key($body,['document'=>true]));
                if ($saved !== $current) $saved['updatedAt'] = gmdate('c');
                $state['slides'][$index] = $saved;
            }
            $slideIds[] = $saved['id'];
        }
        foreach ($state['slides'] as &$slide) if (str_starts_with($slide['automationKey'] ?? '', 'advertising:event:') && !isset($active[$slide['automationKey']]) && ($slide['status'] ?? '') !== 'archived') { $slide['status'] = 'archived'; $slide['updatedAt'] = gmdate('c'); }
        unset($slide);
        $channel = null;
        foreach ($state['channels'] as $index=>$item) if (($item['automationKey'] ?? '') === 'advertising:channel') { $channel = $item; $channelIndex = $index; break; }
        if ($settings['enabled']) {
            $items = array_map(static fn(string $id): array => ['slideId'=>$id,'durationSeconds'=>$settings['durationSeconds'],'transition'=>'inherit'],array_slice($slideIds,0,$settings['maximumEvents']));
            $channelBody = ['name'=>$settings['channelName'],'description'=>'Automatisch: die nächsten '.$settings['maximumEvents'].' passenden Veranstaltungen','status'=>'published','orientation'=>'auto','defaultTransition'=>'fade','repeatEnabled'=>true,'items'=>$items,'totalDurationSeconds'=>count($items)*$settings['durationSeconds'],'tags'=>['Werbung','Automatisch'],'locationIds'=>$settings['locationIds'],'automationKey'=>'advertising:channel','deletedAt'=>null,'archivedAt'=>null];
            if (!$channel || array_replace($channel,$channelBody) !== $channel) $saveChannel($channelBody,$channel['id'] ?? null);
        } elseif ($channel) { $state['channels'][$channelIndex]['status'] = 'paused'; }
        $state['advertisingLastSynchronizedAt'] = gmdate('c');
        return self::status($state);
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
            if (!empty($event['deletedAt']) || !empty($event['archivedAt']) || in_array($event['status'] ?? '',['cancelled','archived'],true) || !isset($types[self::normalizeType((string)($event['eventType'] ?? ''))])) return false;
            $boundary = strtotime((string)($event['eventEnd'] ?? $event['eventStart'] ?? ''));
            return $boundary !== false ? $boundary >= time() : substr(preg_replace('/\D/','',(string)($event['date'] ?? '')),0,8) >= $today;
        }));
        usort($events,static fn(array $a,array $b): int => [($a['eventStart'] ?? $a['date'] ?? ''),$a['title']] <=> [($b['eventStart'] ?? $b['date'] ?? ''),$b['title']]);
        return $events;
    }

    private static function template(): array
    {
        return ['designOrientation'=>'landscape','settings'=>['background'=>['mode'=>'gradient','color1'=>'#17342b','color2'=>'#315b49','direction'=>'135deg'],'logo'=>['src'=>'','position'=>'top-left'],'clock'=>['enabled'=>false,'position'=>'bottom-right']],
            'elements'=>[['id'=>'advertising-image','type'=>'image','x'=>0,'y'=>0,'width'=>100,'height'=>100,'src'=>'','background'=>'#17342b','opacity'=>100,'padding'=>0,'radius'=>0],
                ['id'=>'advertising-event','type'=>'event','x'=>8,'y'=>55,'width'=>84,'height'=>35,'background'=>'rgba(0,0,0,.58)','color'=>'#ffffff','fontSize'=>54,'autoFontSize'=>true,'align'=>'center','fontWeight'=>700,'padding'=>24,'radius'=>16]]];
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
