<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;
final class AdvertisingVideo
{
 public static function input(mixed $value):?array{
  if($value===null||$value==='')return null;if(!is_array($value)||!is_string($value['src']??null)||!trim($value['src'])||strlen($value['src'])>2000)throw new RuntimeException('Bitte einen gültigen Video-Link angeben.',422);
  $src=trim($value['src']);$url=parse_url($src);$safe=$url&&in_array($url['scheme']??'',['http','https'],true)&&!empty($url['host'])&&!isset($url['user'])&&!isset($url['pass']);if(!$safe)$safe=preg_match('#^/media/library/[^?\#\s]+$#',$src)===1;
  if(!$safe||preg_match('/[\x00-\x1f]/',$src))throw new RuntimeException('Das Werbevideo benötigt eine direkte HTTP(S)-Medienadresse.',422);
  $duration=$value['durationSeconds']??null;$start=$value['trimStart']??0;$end=$value['trimEnd']??null;
  foreach([$duration,$start,...($end===null?[]:[$end])] as $n)if(!is_numeric($n)||!is_finite((float)$n))throw new RuntimeException('Die Videolänge fehlt oder der Videoausschnitt ist ungültig.',422);
  $duration=(float)$duration;$start=(float)$start;$end=$end===null?null:(float)$end;$effective=$end===null?$duration-$start:$end-$start;
  if($duration<=0||$duration>86400||$start<0||$start>=$duration||($end!==null&&$end>$duration)||$effective<=0||$effective>86400)throw new RuntimeException('Die Videolänge fehlt oder der Videoausschnitt ist ungültig (höchstens 24 Stunden).',422);
  $result=['src'=>$src,'durationSeconds'=>$duration,'trimStart'=>$start];if($end!==null)$result['trimEnd']=$end;if(isset($value['mediaAssetId'])){if(!is_string($value['mediaAssetId'])||!$value['mediaAssetId']||strlen($value['mediaAssetId'])>200)throw new RuntimeException('Ungültiger Video-Medienverweis.',422);$result['mediaAssetId']=$value['mediaAssetId'];}return $result;
 }
 public static function duration(array $slide,array $assets=[],?string $orientation=null):?float{
  $doc=$slide['document']??[];$elements=$doc['responsiveLayouts'][$orientation??$slide['orientation']??'landscape']??$doc['elements']??[];$longest=0;
  $scan=static function(mixed $v,int $depth=0)use(&$scan,&$longest,$assets):void{if($depth>32||!is_array($v))return;if(array_is_list($v)){foreach($v as $item)$scan($item,$depth+1);return;}
   if(($v['type']??'')==='video'){$meta=$assets[$v['mediaAssetId']??'']['metadata']??[];$start=(float)($meta['trimStart']??$v['trimStart']??0);$end=$meta['trimEnd']??$v['trimEnd']??null;$duration=$v['durationSeconds']??$meta['duration']??$meta['length']??$v['duration']??null;$effective=is_numeric($end)&&(float)$end>$start?(float)$end-$start:(is_numeric($duration)?(float)$duration-$start:0);if(is_finite($effective)&&$effective>0&&$effective<=86400)$longest=max($longest,$effective);}
   foreach(['elements','children'] as $key)if(isset($v[$key]))$scan($v[$key],$depth+1);
  };$scan($elements);if(!$elements&&($slide['type']??'')==='video')$scan(array_replace($doc,['type'=>'video']));return $longest>0?max(.001,round($longest,3)):null;
 }
 public static function elements(array $elements,?array $video,?string $poster=null):array{
  if(!$video)return $elements;foreach($elements as &$e){if(in_array($e['type']??'',['image','video'],true)&&empty($e['src'])){unset($e['mediaNaturalWidth'],$e['mediaNaturalHeight'],$e['eventImageEdit'],$e['mediaAssetId'],$e['trimEnd']);$e=array_replace($e,['type'=>'video','muted'=>true],$video);if($poster)$e['posterSrc']=$poster;}elseif(is_array($e['elements']??null))$e['elements']=self::elements($e['elements'],$video,$poster);}unset($e);return $elements;
 }
}
