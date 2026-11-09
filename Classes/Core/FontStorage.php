<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;

/** Immutable, validated font files outside the extension/plugin update directory. */
final class FontStorage
{
    public function __construct(private readonly string $directory) {}
    public static function decode(mixed $source): array
    {
        if (!is_string($source) || strlen($source)>2700000 || !preg_match('#^data:[a-z0-9.+/-]+;base64,([A-Za-z0-9+/]+={0,2})$#D',$source,$match)) throw new RuntimeException('Ungültige Schriftdatei oder Datei größer als 2 MB.',422);
        $bytes=base64_decode($match[1],true);
        if($bytes===false || base64_encode($bytes)!==$match[1])throw new RuntimeException('Ungültige Schriftdatei.',422);
        return ['bytes'=>$bytes,'font'=>self::validate($bytes)];
    }
    public static function validate(string $bytes): array
    {
        $size=strlen($bytes);if($size<48||$size>2000000)throw new RuntimeException('Eine Schriftdatei darf höchstens 2 MB groß sein.',422);
        $signature=substr($bytes,0,4);$formats=['wOF2'=>['woff2','font/woff2'],'wOFF'=>['woff','font/woff'],'OTTO'=>['opentype','font/otf'],"\0\1\0\0"=>['truetype','font/ttf']];
        if(!isset($formats[$signature]))throw new RuntimeException('Bitte eine WOFF2-, WOFF-, TTF- oder OTF-Schriftdatei auswählen.',422);
        $u16=static fn(int $at):int=>unpack('n',substr($bytes,$at,2))[1];$u32=static fn(int $at):int=>unpack('N',substr($bytes,$at,4))[1];
        $woff=in_array($signature,['wOF2','wOFF'],true);$count=$u16($woff?12:4);
        if(!$count||$count>256)throw new RuntimeException('Die Schriftdatei enthält ein ungültiges Tabellenverzeichnis.',422);
        if($woff){
            $header=$signature==='wOF2'?48:44;
            if($u32(8)!==$size||$u16(14)!==0||$u32(16)>20000000||$u32(16)<12+$count*16)throw new RuntimeException('Die Schriftdatei enthält ungültige Größenangaben.',422);
            $metadata=$signature==='wOF2'?28:24;
            foreach([[$u32($metadata),$u32($metadata+4)],[$u32($metadata+12),$u32($metadata+16)]] as [$offset,$length])if($length&&($offset<$header||$offset+$length>$size))throw new RuntimeException('Die Schriftdatei enthält ungültige Zusatzdaten.',422);
            if($signature==='wOF2'){
                if(!$u32(20)||$u32(20)>$size-$header-$count*2)throw new RuntimeException('Die WOFF2-Schriftdatei ist unvollständig.',422);
            }else{
                if($header+$count*20>$size)throw new RuntimeException('Die WOFF-Schriftdatei ist unvollständig.',422);
                for($i=0;$i<$count;$i++){$record=$header+$i*20;$offset=$u32($record+4);$packed=$u32($record+8);$original=$u32($record+12);if($offset<$header+$count*20||$offset+$packed>$size||$packed>$original||$original>20000000)throw new RuntimeException('Die WOFF-Schrifttabellen sind ungültig.',422);}
            }
        }else{
            $directory=12+$count*16;$tags=[];if($directory>$size)throw new RuntimeException('Die Schrifttabellen fehlen.',422);
            for($i=0;$i<$count;$i++){$record=12+$i*16;$tag=substr($bytes,$record,4);$offset=$u32($record+8);$length=$u32($record+12);if(isset($tags[$tag])||$offset<$directory||$offset+$length>$size)throw new RuntimeException('Die Schrifttabellen sind ungültig.',422);$tags[$tag]=true;}
            foreach(['head','cmap','maxp'] as $tag)if(!isset($tags[$tag]))throw new RuntimeException('Erforderliche Schrifttabellen fehlen.',422);
        }
        return ['id'=>hash('sha256',$bytes),'format'=>$formats[$signature][0],'mime'=>$formats[$signature][1],'size'=>$size];
    }
    public function put(string $bytes): array
    {
        $font=self::validate($bytes);$file=$this->directory.'/'.$font['id'].'.font';
        try{
            if(!is_dir($this->directory)&&!@mkdir($this->directory,0700,true)&&!is_dir($this->directory))throw new \Exception();
            if(!is_file($file)){
                $handle=@fopen($file,'xb');if($handle===false)throw new \Exception();
                try{@chmod($file,0600);if(@fwrite($handle,$bytes)!==strlen($bytes))throw new \Exception();}finally{fclose($handle);}
            }
            if(@hash_file('sha256',$file)!==$font['id'])throw new \Exception();
        }catch(\Throwable){throw new RuntimeException('Die Schriftdatei konnte nicht sicher gespeichert werden.',503);}
        return $font;
    }
    public function file(array $font): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$font['id'] ?? ''))throw new RuntimeException('Schriftart nicht gefunden.',404);
        $file=$this->directory.'/'.$font['id'].'.font';
        if(!is_file($file)||filesize($file)!==($font['size'] ?? 0)||hash_file('sha256',$file)!==$font['id'])throw new RuntimeException('Die Schriftdatei ist nicht verfügbar.',503);
        return ['font'=>$font,'data'=>base64_encode(file_get_contents($file))];
    }
    public static function references(mixed $value,string $id): bool
    {
        if(!is_array($value))return false;if(($value['fontId'] ?? null)===$id||($value['qrCaptionFontId'] ?? null)===$id)return true;
        foreach($value as $item)if(self::references($item,$id))return true;return false;
    }
}
