<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;
require_once __DIR__.'/AdvertisingService.php';

/** Object-level checks shared by both native CMS adapters. */
final class ContentAccess
{
    public static function fail():never { throw new RuntimeException('Diese Aktion ist nicht für eigene Slides oder freigegebene Kanäle erlaubt.',403); }
    public static function ids(array $state,string $userId):array { return $state['contentChannelAccess'][$userId] ?? []; }
    public static function allowed(string $method,string $path):bool
    {
        if($path==='/api/settings/features'&&$method==='GET')return true;
        if($path==='/api/trash'&&$method==='GET')return true;
        if(preg_match('#^/api/trash/slide/[^/]+$#',$path)&&$method==='POST')return true;
        if(in_array($method,['GET','HEAD'],true))return (bool)preg_match('#^/api/(slides|channels|templates|locations|events|event-templates|media|fonts)(?:/|$)#',$path);
        if(preg_match('#^/api/(events|event-templates)(?:/|$)#',$path))return !str_ends_with($path,'/permanent');
        if(in_array($path,['/api/slides','/api/slides/from-media'],true))return $method==='POST';
        if(preg_match('#^/api/slides/[^/]+$#',$path))return in_array($method,['PUT','DELETE'],true);
        if(preg_match('#^/api/slides/[^/]+/(duplicate|versions/\d+/restore)$#',$path))return $method==='POST';
        if(preg_match('#^/api/content-access/channels/[^/]+/slides$#',$path))return $method==='POST';
        return $path==='/api/media/assets'&&$method==='POST';
    }
    public static function own(array $state,string $userId,string $slideId):array
    {
        foreach($state['slides'] ?? [] as $slide)if(($slide['id'] ?? '')===$slideId&&($slide['ownerId'] ?? '')===$userId&&empty($slide['automationKey'])&&empty($slide['virtual'])&&!array_key_exists('menu',$slide['document'] ?? [])&&!array_key_exists('menuPdf',$slide['document'] ?? []))return $slide;
        self::fail();
    }
    public static function check(array $state,string $userId,string $path,array $body,string $method='GET'):void
    {
        $source=[];$archived=false;
        if(preg_match('#^/api/trash/slide/([^/]+)$#',$path,$archive)){$source=self::own($state,$userId,rawurldecode($archive[1]));$archived=true;}
        if(preg_match('#^/api/slides/([^/]+)(?:/|$)#',$path,$match)&&$match[1]!=='from-media')$source=self::own($state,$userId,rawurldecode($match[1]));
        if(preg_match('#^/api/channels/([^/]+)(?:/|$)#',$path,$match)&&!in_array(rawurldecode($match[1]),self::ids($state,$userId),true))self::fail();
        if((str_starts_with($path,'/api/slides')||$archived)&&in_array($method,['POST','PUT'],true)){
            $body=array_replace($source,$body);
            if(isset($body['tags'])&&(!is_array($body['tags'])||count(array_filter($body['tags'],static fn($tag):bool=>!is_string($tag)))))throw new RuntimeException('Bitte gültige Tags auswählen.',422);
            if(AdvertisingService::formats($body))throw new RuntimeException('Werbe-Tags dürfen nur Administratoren oder Redakteure verwalten.',403);
            foreach(['menu','menuPdf','advertisingTemplate'] as $key)if(array_key_exists($key,$body['document'] ?? []))self::fail();
        }
    }
}
