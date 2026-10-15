<?php

declare(strict_types=1);
namespace Kiosky\Core;

use RuntimeException;

/** Focused content snapshots, with one embedded copy of each required media file. */
final class ContentPackage
{
    public const FORMAT = 'kiosky-content-package';
    public const VERSION = 1;
    private const REFERENCES = ['slideId'=>'slides', 'channelId'=>'channels', 'templateId'=>'templates'];
    private const MEDIA_KEYS = ['src','imageSrc','imageUrl','backgroundImage','poster','logo'];

    public function export(array $state, array $selection, string $platform, string $version, ?callable $exportAsset = null): array
    {
        $index = [];
        foreach (['slides','channels','templates','events','locations','mediaAssets','mediaFolders'] as $kind) {
            $index[$kind] = [];
            foreach ($state[$kind] ?? [] as $record) {
                if (!empty($record['deletedAt']) || !empty($record['archivedAt']) || ($record['status'] ?? '') === 'archived') continue;
                $index[$kind][(string)$record['id']] = $record;
            }
        }
        $selected = ['slides'=>[], 'channels'=>[], 'templates'=>[]];
        $queue = [];
        foreach (array_keys($selected) as $kind) {
            $ids = $selection[$kind] ?? [];
            if (!is_array($ids) || count($ids) > 1000) throw new RuntimeException('Ungültige Inhaltsauswahl.',422);
            foreach (array_unique($ids) as $id) {
                if (!is_string($id) || $id === '') throw new RuntimeException('Ungültige Inhaltsauswahl.',422);
                $queue[] = [$kind,$id];
            }
        }
        if (!$queue) throw new RuntimeException('Bitte mindestens eine Slide, einen Kanal oder eine Vorlage auswählen.',422);
        $events = []; $locations = [];
        for ($i=0; $i<count($queue); $i++) {
            if (count($queue)>10000) throw new RuntimeException('Zu viele Abhängigkeiten im Inhaltspaket.',422);
            [$kind,$id] = $queue[$i];
            if (isset($selected[$kind][$id])) continue;
            $record = $index[$kind][$id] ?? null;
            if (!$record) throw new RuntimeException('Ein ausgewählter oder benötigter Inhalt ist nicht verfügbar.',422);
            $selected[$kind][$id] = $record;
            $this->visit($record, function ($value,$key) use (&$queue,&$events,$index): void {
                if (!is_string($value) || $value==='') return;
                if (isset(self::REFERENCES[$key])) $queue[] = [self::REFERENCES[$key],$value];
                if ($key==='eventId') {
                    if (!isset($index['events'][$value])) throw new RuntimeException('Eine benötigte Veranstaltung fehlt.',422);
                    $events[$value] = $index['events'][$value];
                }
            });
            foreach ($record['locationIds'] ?? [] as $id) {
                if (!isset($index['locations'][$id])) throw new RuntimeException('Ein benötigter Standort fehlt.',422);
                $locations[$id] = $index['locations'][$id];
            }
        }
        $sections = ['slides'=>['records'=>array_values($selected['slides']),'templates'=>array_values($selected['templates']),'versions'=>[],'mediaAssets'=>[],'mediaFolders'=>[]]];
        if ($selected['channels']) $sections['channels'] = ['records'=>array_values($selected['channels'])];
        if ($events) $sections['events'] = ['records'=>array_values($events),'scheduleEntries'=>array_values(array_filter($state['eventSchedules'] ?? [],static fn($entry)=>isset($events[$entry['eventId'] ?? ''])))];
        if ($locations) $sections['displays'] = ['locations'=>array_values($locations)];
        $assets = []; $aliases = []; $urls = []; $hashes = []; $folderIds = []; $external = []; $bytes = 0;
        $add = function (array $asset, bool $reuseFile = false) use (&$assets,&$aliases,&$urls,&$hashes,&$folderIds,&$bytes,&$external,$exportAsset): string {
            $original = (string)($asset['src'] ?? '');
            $metadata = $asset['metadata'] ?? [];
            unset($metadata['cms'],$metadata['cmsId']);
            $variant = json_encode([$asset['type']??'image',$metadata],JSON_THROW_ON_ERROR);
            if (preg_match('#^https?://#',$original) && !$exportAsset) {
                $id='external-'.hash('sha256',$original.$variant);
                $aliases[$asset['id']]=$id; $urls[$original]=$id; $external[$original]=true;
                $assets[$id]=['id'=>$id,'name'=>$asset['name'],'type'=>$asset['type'],'src'=>$original,'external'=>true,'metadata'=>$asset['metadata']??[]];
                return $id;
            }
            $native = $exportAsset && preg_match('/^(wp:|fal:)/',(string)$asset['id']);
            $local = $native ? null : $this->localMediaData($original);
            if ($local !== null) $asset['src'] = $local;
            elseif (!str_starts_with($original,'data:')) {
                if (!$exportAsset) throw new RuntimeException('Die benötigte Mediendatei kann nicht vollständig exportiert werden.',422);
                $asset['src'] = $exportAsset($asset);
            }
            $src = (string)$asset['src'];
            $this->validateMedia($asset);
            $hash = hash('sha256',base64_decode(substr($src,strpos($src,',')+1),true));
            $variantKey=$hash.$variant;
            $id = $hashes[$reuseFile?$hash:$variantKey] ?? (string)$asset['id'];
            $aliases[$asset['id']] = $id;
            $urls[$original] = $id; $urls[$src] = $id;
            if (!isset($assets[$id])) {
                $bytes += strlen($src);
                if ($bytes>220000000) throw new RuntimeException('Das Inhaltspaket ist zu groß. Bitte weniger Inhalte gemeinsam exportieren.',413);
                $asset['id'] = $id;
                $asset['metadata']=$metadata;
                $assets[$id] = $asset;
                $hashes[$variantKey] = $id;
                $hashes[$hash] ??= $id;
                if (!empty($asset['folderId'])) $folderIds[$asset['folderId']] = true;
            }
            return $id;
        };
        $this->visit($sections, function ($value,$key) use (&$aliases,$index,$add): void {
            if ($key!=='mediaAssetId' || !is_string($value) || $value==='' || isset($aliases[$value])) return;
            if (!isset($index['mediaAssets'][$value])) throw new RuntimeException('Eine benötigte Mediendatei fehlt.',422);
            $add($index['mediaAssets'][$value]);
        });
        $assetUrls = [];
        foreach ($index['mediaAssets'] as $asset) $assetUrls[$asset['src'] ?? ''] = $asset;
        $this->visit($sections, function ($value,$key) use (&$urls,&$external,$assetUrls,$add): void {
            if (!in_array($key,self::MEDIA_KEYS,true) || !is_string($value) || $value==='' || $value==='none' || isset($urls[$value])) return;
            if (isset($assetUrls[$value])) { $add($assetUrls[$value]); return; }
            if (str_starts_with($value,'data:')) {
                $add(['id'=>'inline-'.hash('sha256',$value),'name'=>'Eingebettetes Medium','type'=>str_starts_with($value,'data:video/')?'video':(str_starts_with($value,'data:image/svg')?'icon':(str_starts_with($value,'data:image/')?'image':'file')),'src'=>$value,'metadata'=>[]],true);
            } elseif (preg_match('#(?:^|/)media/(arrows|alerts)/#',$value)) {
                $id = $add(['id'=>'local-'.hash('sha256',$value),'name'=>basename($value),'src'=>$value,'type'=>str_ends_with($value,'.svg')?'icon':'image','metadata'=>[]],true);
                $urls[$value] = $id;
            } elseif (preg_match('#^https?://#',$value)) $external[$value]=true;
            elseif (!str_starts_with($value,'#')) throw new RuntimeException('Eine lokale Mediendatei ist nicht in der Medienbibliothek verfügbar.',422);
        });
        $sections = $this->rewrite($sections,static fn($value,$key)=>!is_string($value)?$value:($key==='mediaAssetId'?($aliases[$value]??$value):(isset($urls[$value])?'kiosky-asset:'.$urls[$value]:$value)));
        $sections['slides']['mediaAssets'] = array_values($assets);
        $folders = []; $pending = array_keys($folderIds);
        for ($i=0; $i<count($pending); $i++) {
            $id=$pending[$i];
            if (isset($folders[$id])) continue;
            if (!isset($index['mediaFolders'][$id])) throw new RuntimeException('Ein benötigter Medienordner fehlt.',422);
            $folder=$index['mediaFolders'][$id];
            unset($folder['system']);
            $folders[$id]=$folder;
            if (!empty($folder['parentId'])) $pending[]=$folder['parentId'];
        }
        $sections['slides']['mediaFolders']=array_values($folders);
        if (strlen(json_encode($sections,JSON_THROW_ON_ERROR))>250000000) throw new RuntimeException('Das Inhaltspaket ist zu groß.',413);
        return ['format'=>self::FORMAT,'version'=>self::VERSION,'exportedAt'=>gmdate('c'),'source'=>['platform'=>$platform,'version'=>$version],'selection'=>$selection,'sections'=>$sections,'notices'=>$external?[count($external).' externe Medien-URLs bleiben als Links erhalten und benötigen die ursprünglichen Server.']:[]];
    }

    public function prepare(array $package): array
    {
        if (($package['format']??'')!==self::FORMAT || ($package['version']??0)!==self::VERSION || !is_array($package['sections']??null)) throw new RuntimeException('Ungültiges Kiosky-Inhaltspaket.',422);
        $sections=$package['sections'];
        if (strlen(json_encode($sections,JSON_THROW_ON_ERROR))>250000000) throw new RuntimeException('Das Inhaltspaket ist zu groß.',413);
        $allowed=['slides'=>['records','templates','versions','mediaAssets','mediaFolders'],'channels'=>['records'],'events'=>['records','scheduleEntries'],'displays'=>['locations']];
        foreach ($sections as $section=>&$data) {
            if (!isset($allowed[$section]) || !is_array($data)) throw new RuntimeException('Inhaltspakete dürfen ausschließlich Inhalte und deren Abhängigkeiten enthalten.',422);
            foreach ($data as $key=>$records) {
                if (!in_array($key,$allowed[$section],true) && $records===[]) { unset($data[$key]); continue; }
                if (!in_array($key,$allowed[$section],true) || !is_array($records) || count($records)>10000) throw new RuntimeException('Ungültige Sammlung im Inhaltspaket.',422);
            }
        }
        unset($data);
        $assets=[];
        foreach ($sections['slides']['mediaAssets']??[] as $asset) {
            if (!is_array($asset) || !is_string($asset['id']??null) || isset($assets[$asset['id']])) throw new RuntimeException('Ungültige Mediendatei.',422);
            if (!(($asset['external']??false)===true && preg_match('#^https?://#',(string)($asset['src']??'')))) {
                $this->validateMedia($asset);
                // Validate file bytes and SVGs before the state store writes any CMS files.
                MediaUpload::decode($asset);
            }
            $assets[$asset['id']]=$asset;
        }
        $ids=[];
        foreach (['slides'=>$sections['slides']['records']??[],'templates'=>$sections['slides']['templates']??[],'channels'=>$sections['channels']['records']??[],'events'=>$sections['events']['records']??[]] as $kind=>$records) {
            $ids[$kind]=array_fill_keys(array_column($records,'id'),true);
        }
        if (!$ids['slides'] && !$ids['templates'] && !$ids['channels']) throw new RuntimeException('Das Inhaltspaket enthält keine Slides, Kanäle oder Vorlagen.',422);
        $this->visit($sections,function($value,$key) use($assets,$ids): void {
            if (!is_string($value) || $value==='') return;
            $kind=self::REFERENCES[$key]??($key==='eventId'?'events':($key==='mediaAssetId'?'mediaAssets':null));
            if ($kind && !isset(($kind==='mediaAssets'?$assets:$ids[$kind])[$value])) throw new RuntimeException('Das Inhaltspaket enthält einen fehlenden Inhaltsverweis.',422);
            if (str_starts_with($value,'kiosky-asset:') && !isset($assets[substr($value,13)])) throw new RuntimeException('Im Inhaltspaket fehlt eine Mediendatei.',422);
        });
        $expand=function(mixed $value) use(&$expand,$assets): mixed {
            if (!is_array($value)) return is_string($value)&&str_starts_with($value,'kiosky-asset:')?$assets[substr($value,13)]['src']:$value;
            foreach ($value as &$entry) $entry=$expand($entry);
            unset($entry);
            $asset=$assets[$value['mediaAssetId']??'']??null;
            if (!empty($asset['external'])) {
                unset($value['mediaAssetId']);
                $value[array_key_exists('imageSrc',$value)?'imageSrc':'src']=$asset['src'];
                if (($asset['type']??'')==='video') {
                    $value['trimStart']=$asset['metadata']['trimStart']??0;
                    unset($value['trimEnd']);
                    if (isset($asset['metadata']['trimEnd'])) $value['trimEnd']=$asset['metadata']['trimEnd'];
                }
            }
            return $value;
        };
        $sections=$expand($sections);
        // Portable copies must not be owned or archived by the destination's advertising automation.
        foreach (['slides','channels'] as $kind) foreach ($sections[$kind]['records']??[] as $key=>$record) {
            unset($record['automationKey']);
            $sections[$kind]['records'][$key]=$record;
        }
        if (isset($sections['slides'])) $sections['slides']['mediaAssets']=array_values(array_filter($sections['slides']['mediaAssets']??[],static fn($asset)=>empty($asset['external'])));
        foreach ($sections['events']['records']??[] as $key=>$event) {
            unset($event['sourceId'],$event['externalId'],$event['externalObjectType'],$event['sourceUpdatedAt'],$event['lastSyncedAt']);
            $event['syncStatus']='manual'; $event['lockedFields']=[];
            $sections['events']['records'][$key]=$event;
        }
        return $sections;
    }

    public function import(array &$state,array $package): array
    {
        $sections=$this->prepare($package);
        $result=(new TransferService())->import($state,['format'=>TransferService::FORMAT,'version'=>TransferService::VERSION,'sections'=>$sections]);
        $result['notices']=$package['notices']??[];
        return $result;
    }

    private function localMediaData(string $value): ?string
    {
        if (!preg_match('#(?:^|/)(media/(?:arrows|alerts)/[^?\#]+)(?:[?\#].*)?$#',$value,$match)) return null;
        $root = realpath(__DIR__.'/../../assets/app') ?: realpath(__DIR__.'/../../Resources/Public/App') ?: realpath(__DIR__.'/../../../public');
        $path = $root ? realpath($root.'/'.$match[1]) : false;
        if (!$path || !str_starts_with($path,$root.'/media/') || !is_file($path) || filesize($path)>20000000) throw new RuntimeException('Eine lokale Mediendatei ist nicht verfügbar.',422);
        $mime = str_ends_with($path,'.svg') ? 'image/svg+xml' : (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    private function validateMedia(array $asset): void
    {
        $src=(string)($asset['src']??'');
        if (!preg_match('#^data:[\w.+/-]+;base64,([A-Za-z0-9+/=\r\n]+)$#',$src,$match) || strlen($match[1])>26666672 || base64_decode($match[1],true)===false) throw new RuntimeException('Ungültige oder zu große Mediendatei (maximal 20 MB).',422);
    }
    private function visit(mixed $value,callable $callback,string $key=''): void
    {
        $callback($value,$key);
        if (is_array($value)) foreach ($value as $name=>$entry) $this->visit($entry,$callback,is_string($name)?$name:$key);
    }
    private function rewrite(mixed $value,callable $callback,string $key=''): mixed
    {
        if (!is_array($value)) return $callback($value,$key);
        foreach ($value as $name=>&$entry) $entry=$this->rewrite($entry,$callback,is_string($name)?$name:$key);
        return $value;
    }
}
