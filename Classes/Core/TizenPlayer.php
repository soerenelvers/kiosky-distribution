<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class TizenPlayer {
 public static function entries(string $playerUrl,string $root,array $runtime):array {
  $url=parse_url($playerUrl);if(!$url||!in_array($url['scheme']??'',['http','https'],true)||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||preg_match('/[\x00-\x20"<>]/',$playerUrl))throw new \RuntimeException('Ungültige TV-Serveradresse.',422);
  $origin=$url['scheme'].'://'.$url['host'].(isset($url['port'])?':'.$url['port']:'');if(!preg_match('#^[a-zA-Z0-9.:/\[\]-]+$#',$origin))throw new \RuntimeException('Ungültiger Servername.',422);
  $versionFile=$root.'/../../build-info.json';$version=is_readable($versionFile)?(json_decode((string)file_get_contents($versionFile),true)['version']??''):trim((string)file_get_contents(__DIR__.'/../../../VERSION'));
  if(!preg_match('/^\d+\.\d+\.\d+$/',$version))throw new \RuntimeException('TV-Versionsmetadaten fehlen.',503);
  if(empty($runtime['playerBaseUrl']))throw new \RuntimeException('TV-Serverkonfiguration fehlt.',503);
  foreach(['apiEndpoint','playerBaseUrl'] as $field)if(!empty($runtime[$field])&&!str_starts_with($runtime[$field],$origin.'/'))throw new \RuntimeException('TV-Serverkonfiguration ist ungültig.',422);
  $entries=[];foreach(['index.html','native.js','shell.css','icon.png','config.xml'] as $name){$data=self::file($root.'/app/'.$name);if($name==='config.xml')$data=str_replace(['__VERSION__','__ORIGIN__'],[$version,$origin],$data);$entries[]=['app/'.$name,$data,0100644];}
  $entries[]=['app/binding.json',json_encode(['origin'=>$origin]+$runtime,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),0100644];
  foreach(['install.sh','install.ps1','README.md'] as $name)$entries[]=[$name,self::file($root.'/'.$name),str_ends_with($name,'.sh')?0100755:0100644];return $entries;
 }
 private static function file(string $path):string{$data=@file_get_contents($path);if($data===false)throw new \RuntimeException('TV-Dateien fehlen im Installationspaket.',503);return $data;}
 public static function document(string $html,string $assetBase,mixed $display,mixed $key):string {
  if(!is_string($display)||trim($display)===''||strlen($display)>200||!is_string($key)||$key===''||strlen($key)>500)throw new \RuntimeException('Eine gültige Display-Kopplung ist erforderlich.',400);
  if(!preg_match('#<head>([\s\S]*?)</head>#i',$html,$head))throw new \RuntimeException('TV-Dokument fehlt.',503);preg_match('#app\.js\?v=([^"<>]+)#',$html,$build);$version=$build[1]??'0';
  $base=htmlspecialchars($assetBase,ENT_QUOTES);return '<!doctype html><html lang="de"><head>'.preg_replace('#(?:href="[^"]*styles\.css)#','href="'.$base.'player/tizen/compat.css',preg_replace('#<script\b[\s\S]*?</script>#i','',$head[1])).'<link rel="stylesheet" href="'.$base.'player/tizen/tv.css?v='.$version.'"></head><body class="player-mode"><script src="'.$base.'player/tizen/compat.js?v='.$version.'"></script></body></html>';
 }
 public static function pairingHeaders():array{return ['Access-Control-Allow-Origin'=>'*','Access-Control-Allow-Methods'=>'GET, POST, OPTIONS','Access-Control-Allow-Headers'=>'Content-Type, X-Pairing-Secret, X-Player-Key','Access-Control-Max-Age'=>'600'];}
}
