<?php
declare(strict_types=1);
namespace Kiosky\Core;
final class PublicationCheck {
 public static function check(array $slide,array $media=[],?array $event=null):array{
  $issues=[];$doc=$slide['document']??[];$layouts=[];
  $add=static function(string $level,string $code,string $message,?array $element=null,?string $layout=null)use(&$issues):void{$row=['level'=>$level,'code'=>$code,'message'=>$message];if($element!==null)$row['elementId']=(string)($element['id']??'');if($layout!==null)$row['layout']=$layout;$issues[]=$row;};
  if(trim((string)($slide['name']??''))==='')$add('error','missing-name','Bitte einen Namen für die Slide angeben.');
  if(!empty($slide['startsAt'])&&!empty($slide['endsAt'])&&strtotime($slide['endsAt'])<=strtotime($slide['startsAt']))$add('error','invalid-period','Das Sichtbarkeitsende muss nach dem Beginn liegen.');
  if(!empty($slide['endsAt'])&&strtotime($slide['endsAt'])<=time())$add('warning','expired','Die Slide ist bereits abgelaufen und wird nicht ausgespielt.');
  foreach(['landscape','portrait'] as $name)if(isset($doc['responsiveLayouts'][$name])&&is_array($doc['responsiveLayouts'][$name]))$layouts[$name]=$doc['responsiveLayouts'][$name];
  if(!$layouts)$layouts[($slide['orientation']??'')==='portrait'?'portrait':'landscape']=$doc['elements']??[];$assets=array_column($media,null,'id');
  foreach($layouts as $layout=>$elements){$width=$layout==='portrait'?1080:1920;$height=$layout==='portrait'?1920:1080;$seen=[];
   if(!$elements&&empty($doc['imageSrc'])&&empty($doc['videoSrc'])&&empty($doc['url'])&&empty($doc['settings']['imageSrc'])&&empty($doc['menu'])&&empty($doc['menuPdf']))$add('warning','empty-layout','Dieses Format enthält keine sichtbaren Elemente.',null,$layout);
   foreach($elements as $e){if(!is_array($e))continue;$id=(string)($e['id']??'');$type=$e['type']??'';if($id!==''&&isset($seen[$id]))$add('error','duplicate-id','Mehrere Elemente haben dieselbe Kennung.',$e,$layout);$seen[$id]=true;
    $x=(float)($e['x']??0);$y=(float)($e['y']??0);$w=(float)($e['width']??40);$h=(float)($e['height']??20);
    if(!is_finite($x)||!is_finite($y)||!is_finite($w)||!is_finite($h)||$w<=0||$h<=0)$add('error','invalid-size','Das Element hat keine gültige Größe.',$e,$layout);elseif($x<0||$y<0||$x+$w>100.1||$y+$h>100.1)$add('warning','outside-slide','Das Element ragt über die Slide hinaus.',$e,$layout);
    if(in_array($type,['image','video','web'],true)){$asset=$assets[$e['mediaAssetId']??'']??null;$src=trim((string)(($e['src']??'')?:($asset['src']??'')?:($type==='image'?($event['imageUrl']??''):'')));
     if(!empty($asset['deletedAt']))$add('error','deleted-media','Das verwendete Medium wurde gelöscht.',$e,$layout);
     if($src==='')$add('warning','missing-media','Bild, Video oder Website hat keine Quelle. Bei Eventbildern die Veranstaltung prüfen.',$e,$layout);
     elseif(!str_starts_with($src,'/')&&!preg_match('#^data:(image|video)/#i',$src)){$url=parse_url($src);if(!$url||!in_array(strtolower($url['scheme']??''),['http','https'],true)||empty($url['host'])||isset($url['user'])||isset($url['pass']))$add('error','invalid-source','Die Medienadresse ist ungültig.',$e,$layout);}
     if($type==='web')$add('info','external-unverified','Externe Websites können Einbettungen sperren. Die Erreichbarkeit wird nicht serverseitig geprüft.',$e,$layout);
    }
    $font=(float)($e['fontSize']??32);$text=(string)($e['text']??'');
    if(in_array($type,['text','event','event-field','event-table','event-stamp','ticker','countdown'],true)&&$font<18)$add('warning','small-text','Die Schrift ist kleiner als 18 Designpixel. Lesbarkeit am Display prüfen.',$e,$layout);
    if($type==='text'&&$text!==''&&$font>0){$chars=max(1,(int)floor(($w/100*$width)/($font*.55)));$lines=0;foreach(explode("\n",$text) as $line)$lines+=max(1,(int)ceil(preg_match_all('/./us',$line)/$chars));if($lines*$font*(float)($e['lineHeight']??1.1)>$h/100*$height*1.15)$add('warning','text-overflow','Der Text könnte den verfügbaren Bereich überschreiten. Vorschau und automatische Textanpassung prüfen.',$e,$layout);}
    if($type==='qr-code'||$type==='event-field'&&($e['field']??'')==='ticketQr'){
     if(min($w/100*$width,$h/100*$height)<96)$add('warning','small-qr','Der QR-Code ist kleiner als 96 Designpixel. Mit einem Smartphone am Display testen.',$e,$layout);
     if(($e['qrKind']??'')==='event-ticket'||($e['field']??'')==='ticketQr'){if(trim((string)($event['ticketUrl']??''))==='')$add('info','missing-ticket','Ohne Ticket-Link werden Ticket-QR-Code und Begleittext ausgeblendet.',$e,$layout);elseif(in_array($event['status']??'',['draft','archived','removed','cancelled'],true))$add('warning','ticket-event-status','Die Veranstaltung ist nicht für den öffentlichen Ticketverkauf freigegeben.',$e,$layout);}
     elseif(!in_array($e['qrKind']??'',['wifi','phone','email'],true)&&trim((string)($e['qrValue']??''))==='')$add('warning','empty-qr','Für den QR-Code fehlt ein Inhalt.',$e,$layout);
    }
   }
  }
  return ['issues'=>$issues,'errors'=>count(array_filter($issues,static fn(array $r):bool=>$r['level']==='error')),'warnings'=>count(array_filter($issues,static fn(array $r):bool=>$r['level']==='warning')),'checkedLayouts'=>array_keys($layouts),'externalResourcesChecked'=>false];
 }
}
