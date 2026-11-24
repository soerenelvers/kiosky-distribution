<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class PlayerRequest {
 public static function limit(string $path):?int {return preg_match('#^/api/player/[^/]+/capture$#D',$path)?2801000:(preg_match('#^/api/player-pairings(?:/[^/]+)?$#',$path)?8192:(preg_match('#^/api/player/[^/]+/(state|heartbeat|proof|commands|capture)$#',$path)?131072:null));}
 public static function decode(string $raw,string $path):array{
  $limit=self::limit($path);if($limit!==null&&strlen($raw)>$limit)throw new \RuntimeException('Player-Anfrage ist zu groß.',413);
  if(trim($raw)==='')return [];try{$object=json_decode($raw,false,32,JSON_THROW_ON_ERROR);$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \RuntimeException('Ungültiges JSON.',400);}if(!$object instanceof \stdClass||!is_array($body))throw new \RuntimeException('JSON-Objekt erforderlich.',400);return $body;
 }
}
