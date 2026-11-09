<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;
/** Same-origin bounded redirects: never forward provider credentials to another server. */
final class ProviderHttp
{
    public const LIMIT=20000000;
    private static function origin(string $url):string
    {
        $parts=parse_url($url);$scheme=strtolower($parts['scheme'] ?? '');
        if(!$parts||!in_array($scheme,['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||preg_match('/[\x00-\x20\x7f\\\\]/',$url))throw new RuntimeException('Ungültige Adresse des externen Dienstes.',422);
        return $scheme.'://'.strtolower($parts['host']).':'.($parts['port'] ?? ($scheme==='https'?443:80));
    }
    private static function target(string $base,string $location):string
    {
        if(preg_match('#^https?://#i',$location))return $location;
        $parts=parse_url($base);$authority=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
        if(str_starts_with($location,'//'))return $parts['scheme'].':'.$location;
        if(str_contains(explode('/',$location)[0],':'))throw new RuntimeException('Ungültige Weiterleitung des externen Dienstes.',502);
        if(str_starts_with($location,'?'))return $authority.($parts['path'] ?? '/').$location;
        $path=str_starts_with($location,'/')?$location:substr($parts['path'] ?? '/',0,strrpos($parts['path'] ?? '/','/')+1).$location;
        $segments=[];foreach(explode('/',$path) as $part){if($part==='..')array_pop($segments);elseif($part!=='.')$segments[]=$part;}
        return $authority.implode('/',$segments);
    }
    public static function request(callable $send,string $method,string $url,array $headers,?string $body,int $timeoutSeconds=15):HttpResponse
    {
        $origin=self::origin($url);$deadline=microtime(true)+max(1,$timeoutSeconds);
        for($count=0;$count<=3;$count++){
            $remaining=(int)ceil($deadline-microtime(true));if($remaining<=0)throw new RuntimeException('Der externe Dienst hat das Zeitlimit überschritten.',504);
            $response=$send($method,$url,$headers,$body,$remaining);
            if(!in_array($response->status,[301,302,303,307,308],true))return $response;
            $location=$response->header('Location');if($count===3||$location==='')throw new RuntimeException('Der externe Dienst hat zu viele oder ungültige Weiterleitungen geliefert.',502);
            $next=self::target($url,$location);if(self::origin($next)!==$origin)throw new RuntimeException('Eine Weiterleitung an einen anderen Server wurde zum Schutz der Zugangsdaten abgewiesen. Bitte die direkte Basis-URL konfigurieren.',502);
            if($response->status===303||(in_array($response->status,[301,302],true)&&$method==='POST')){$method='GET';$body=null;foreach(array_keys($headers) as $key)if(in_array(strtolower($key),['content-type','content-length'],true))unset($headers[$key]);}
            $url=$next;
        }
        throw new RuntimeException('Ungültige Weiterleitung.',502);
    }
}
