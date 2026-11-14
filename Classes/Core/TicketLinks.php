<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class TicketLinks
{
 public static function url(mixed $value):?string{
  if(!is_string($value))return null;$value=trim($value);
  if($value===''||strlen($value)>2000||preg_match('/[\x00-\x20\x7f\\\\]/',$value))return null;
  $url=parse_url($value);return is_array($url)&&in_array(strtolower($url['scheme']??''),['http','https'],true)&&!empty($url['host'])&&empty($url['user'])&&empty($url['pass'])&&filter_var($value,FILTER_VALIDATE_URL)?$value:null;
 }
 public static function normalize(mixed $value,bool $strict=true):array{
  $fail=static function():void{throw new \RuntimeException('Bitte höchstens 10 Ticket-Links mit Anbietername (max. 80 Zeichen) und gültiger HTTP-/HTTPS-Adresse angeben.',422);};
  if(!is_array($value)||!array_is_list($value)||count($value)>10){if($strict)$fail();return [];}
  $links=[];foreach($value as $raw){if(!is_array($raw)){if($strict)$fail();continue;}
   $label=is_string($raw['label']??null)?trim($raw['label']):'';$text=is_string($raw['url']??null)?trim($raw['url']):'';
   if((array_key_exists('url',$raw)&&!is_string($raw['url']))||(array_key_exists('label',$raw)&&!is_string($raw['label']))){if($strict)$fail();continue;}
   if($label===''&&$text==='')continue;$url=self::url($text);$length=preg_match_all('/./us',$label)+preg_match_all('/[\x{10000}-\x{10FFFF}]/u',$label);
   if(!$url||$length>80||preg_match('/[\x00-\x1f\x7f]/',$label)||(array_key_exists('label',$raw)&&!is_string($raw['label']))){if($strict)$fail();continue;}
   if(!in_array($url,array_column($links,'url'),true))$links[]=['label'=>$label,'url'=>$url];
  }return $links;
 }
 public static function links(array $event):array{$links=self::normalize($event['ticketLinks']??null,false);$url=self::url($event['ticketUrl']??null);return $links?:($url?[['label'=>'','url'=>$url]]:[]);}
 public static function apply(array $input,array $current=[]):array{
  if(array_key_exists('ticketLinks',$input)){$links=self::normalize($input['ticketLinks']);if(isset($current['ticketLinks'])&&$links===$current['ticketLinks']&&array_key_exists('ticketUrl',$input)&&($input['ticketUrl']??null)!==($links[0]['url']??null)){if(trim((string)($input['ticketUrl']??''))==='')array_shift($links);else{$url=self::url($input['ticketUrl']);if(!$url)throw new \RuntimeException('Der Ticket-Link muss eine gültige HTTP-/HTTPS-Adresse sein.',422);$links[0]=['label'=>$links[0]['label']??'','url'=>$url];}}if(!$links&&($url=self::url($input['ticketUrl']??null)))$links[]=['label'=>'','url'=>$url];return array_replace($input,['ticketLinks'=>$links,'ticketUrl'=>$links[0]['url']??null]);}
  if(empty($current['ticketLinks']))return $input;
  if(($input['ticketUrl']??null)==='')return array_replace($input,['ticketLinks'=>[],'ticketUrl'=>null]);
  $links=self::normalize($current['ticketLinks']);if(isset($input['ticketUrl'])&&$input['ticketUrl']!==($links[0]['url']??null)){$url=self::url($input['ticketUrl']);if(!$url)throw new \RuntimeException('Der Ticket-Link muss eine gültige HTTP-/HTTPS-Adresse sein.',422);$links[0]=['label'=>$links[0]['label']??'','url'=>$url];}
  return array_replace($input,['ticketLinks'=>$links,'ticketUrl'=>$links[0]['url']??null]);
 }
}
