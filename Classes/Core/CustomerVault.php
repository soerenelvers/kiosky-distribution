<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;
/** Private immutable quarantine; no web-accessible file names or customer-supplied paths. */
final class CustomerVault {
    public function __construct(private readonly string $directory){
    }
    private function directory():void{
        if(is_link($this->directory)||(!is_dir($this->directory)&&!@mkdir($this->directory,0700,true)&&!is_dir($this->directory)))throw new RuntimeException('Privater Uploadspeicher ist nicht verfügbar.',503);
    }
    private function path(string $id):string{
        if(!preg_match('/^[a-f0-9]{64}$/D',$id))throw new RuntimeException('Datei nicht gefunden.',404);
        return $this->directory.'/'.$id.'.upload';
    }
    private static function settingBytes(string $key):int{
        $raw=trim((string)ini_get($key));
        if($raw===''||$raw==='-1'||$raw==='0')return PHP_INT_MAX;
        $n=(int)$raw;
        return $n*match(strtolower(substr($raw,-1))){
            'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1
        }
        ;
    }
    public static function maximumBytes():int{
        return max(0,min(20000000,self::settingBytes('upload_max_filesize'),(int)floor(self::settingBytes('memory_limit')/12),(int)floor(max(0,self::settingBytes('post_max_size')-4096)*.74)));
    }
    public static function assertDelivery(int $bytes):void{
        $limit=self::settingBytes('memory_limit');
        if($limit!==PHP_INT_MAX&&memory_get_usage(true)+$bytes*5+8000000>$limit)throw new RuntimeException('Für diese Vorschau reicht der Server-Arbeitsspeicher nicht aus. Bitte eine kleinere Datei verwenden.',413);
    }
    public static function assertRequest(int $bytes):void{
        $limit=self::settingBytes('memory_limit');
        if($limit!==PHP_INT_MAX&&memory_get_usage(true)+$bytes*2+16000000>$limit)throw new RuntimeException('Die Datei ist für diesen Server zu groß. Bitte eine kleinere Datei wählen.',413);
    }
    public static function assertImport(string $source):void{
        $media=self::decode($source);
        $pixels=0;
        if(str_starts_with($media['mime'],'image/')){
            $size=@getimagesizefromstring($media['bytes']);
            $pixels=($size[0]??0)*($size[1]??0);
        }
        $limit=self::settingBytes('memory_limit');
        if($limit!==PHP_INT_MAX&&memory_get_usage(true)+$pixels*5+strlen($media['bytes'])*2+8000000>$limit)throw new RuntimeException('Für dieses Medium reicht der Server-Arbeitsspeicher nicht aus. Bitte die Datei verkleinern.',413);
    }
    public function lock():mixed{
        $this->directory();
        $p=$this->directory.'/.lock';
        if(is_link($p))throw new RuntimeException('Uploadspeicher nicht verfügbar.',503);
        $h=@fopen($p,'c');
        if(!$h||!@flock($h,LOCK_EX))throw new RuntimeException('Uploadspeicher ist belegt.',503);
        @chmod($p,0600);
        return $h;
    }
    public function rate(string $address):void{
        $p=$this->directory.'/.rate';
        if(is_link($p))throw new RuntimeException('Uploadspeicher nicht verfügbar.',503);
        $raw=is_file($p)?@file_get_contents($p):'{}';
        if($raw===false)throw new RuntimeException('Uploadspeicher nicht verfügbar.',503);
        $rows=json_decode($raw,true);
        if(!is_array($rows))$rows=[];
        $now=time();
        $rows=array_filter($rows,static fn($v)=>is_array($v)&&($v['until']??0)>$now);
        $key=hash('sha256',$address);
        if(count($rows)>=512&&!isset($rows[$key]))throw new RuntimeException('Bitte später erneut versuchen.',429);
        $v=$rows[$key]??['count'=>0,'until'=>$now+60];
        if(++$v['count']>120)throw new RuntimeException('Zu viele Anfragen. Bitte eine Minute warten.',429);
        $rows[$key]=$v;
        if(@file_put_contents($p,json_encode($rows),LOCK_EX)===false)throw new RuntimeException('Uploadspeicher nicht verfügbar.',503);
        @chmod($p,0600);
    }
    public static function decode(mixed $source):array{
        if(!is_string($source)||strlen($source)>27000000)throw new RuntimeException('Eine Datei darf höchstens 20 MB groß sein.',413);
        if(!preg_match('#^data:([a-z0-9.+/-]+);base64,([A-Za-z0-9+/]+={0,2})$#D',$source,$m))throw new RuntimeException('Bitte eine Bild- oder Videodatei hochladen.',422);
        $estimate=(int)(strlen($m[2])*3/4);
        $limit=self::settingBytes('memory_limit');
        if($limit!==PHP_INT_MAX&&memory_get_usage(true)+$estimate*2+8000000>$limit)throw new RuntimeException('Die Datei ist für diesen Server zu groß. Bitte eine kleinere Datei wählen.',413);
        $b=base64_decode($m[2],true);
        if($b===false||$b===''||strlen($b)>20000000||base64_encode($b)!==$m[2])throw new RuntimeException('Ungültige Datei oder Datei größer als 20 MB.',422);
        $len=strlen($b);
        $mime='';
        $ext='';
        $u32=static fn($at)=>unpack('N',substr($b,$at,4))[1];
        $le32=static fn($at)=>unpack('V',substr($b,$at,4))[1];
        $le16=static fn($at)=>unpack('v',substr($b,$at,2))[1];
        if($len>=67&&substr($b,0,8)===hex2bin('89504e470d0a1a0a')&&substr($b,12,4)==='IHDR'&&substr($b,-8,4)==='IEND'&&$u32(16)>0&&$u32(20)>0){
            $mime='image/png';
            $ext='png';
        }
        elseif($len>=20&&substr($b,0,2)==="\xff\xd8"&&substr($b,-2)==="\xff\xd9"){
            $mime='image/jpeg';
            $ext='jpg';
        }
        elseif($len>=14&&in_array(substr($b,0,6),['GIF87a','GIF89a'],true)&&substr($b,-1)===';'&&$le16(6)>0&&$le16(8)>0){
            $mime='image/gif';
            $ext='gif';
        }
        elseif($len>=30&&substr($b,0,4)==='RIFF'&&substr($b,8,4)==='WEBP'&&$le32(4)+8===$len&&in_array(substr($b,12,4),['VP8 ','VP8L','VP8X'],true)){
            $mime='image/webp';
            $ext='webp';
        }
        elseif($len>=32&&substr($b,4,4)==='ftyp'&&$u32(0)>=16&&$u32(0)<=$len&&in_array(substr($b,8,4),['isom','iso2','iso5','iso6','mp41','mp42','avc1','M4V ','qt  '],true)){
            $mime=substr($b,8,4)==='qt  '?'video/quicktime':'video/mp4';
            $ext=$mime==='video/quicktime'?'mov':'mp4';
        }
        elseif($len>=32&&substr($b,0,4)===hex2bin('1a45dfa3')&&str_contains(substr($b,4,4092),'webm')){
            $mime='video/webm';
            $ext='webm';
        }
        if($mime===''||$mime!==$m[1])throw new RuntimeException('Erlaubt sind PNG, JPG, GIF, WebP, MP4, MOV und WebM mit passendem Dateiinhalt.',422);
        if(str_starts_with($mime,'image/')){
            $size=@getimagesizefromstring($b);
            if(!$size||$size[0]<1||$size[1]<1||$size[0]>8192||$size[1]>8192||$size[0]*$size[1]>16000000)throw new RuntimeException('Das Bild ist ungültig oder überschreitet 16 Megapixel bzw. 8192 Pixel je Seite.',422);
        }
        return ['bytes'=>$b,'mime'=>$mime,'extension'=>$ext,'type'=>str_starts_with($mime,'video/')?'video':'image','hash'=>hash('sha256',$b)];
    }
    public function put(string $invitationId,array $media):string{
        $this->directory();
        $id=hash('sha256',$invitationId.'/'.$media['hash']);
        $p=$this->path($id);
        if(is_file($p)){
            if(is_link($p)||@file_get_contents($p)!==$media['bytes'])throw new RuntimeException('Uploadspeicher ist nicht verfügbar.',503);
            return $id;
        }
        $files=glob($this->directory.'/*.upload')?:[];
        $bytes=0;
        foreach($files as $f){
            if(is_link($f))throw new RuntimeException('Uploadspeicher ist nicht verfügbar.',503);
            $bytes+=filesize($f);
        }
        if(count($files)>=2000||$bytes+strlen($media['bytes'])>216000000)throw new RuntimeException('Der Speicher für ungeprüfte Uploads ist voll. Bitte den Mitarbeiter kontaktieren.',507);
        $h=@fopen($p,'xb');
        if(!$h)throw new RuntimeException('Datei konnte nicht gespeichert werden.',503);
        @chmod($p,0600);
        try{
            $at=0;
            $length=strlen($media['bytes']);
            while($at<$length){
                $written=@fwrite($h,substr($media['bytes'],$at));
                if(!$written)throw new RuntimeException('Datei konnte nicht vollständig gespeichert werden.',503);
                $at+=$written;
            }
        }
        finally{
            fclose($h);
        }
        return $id;
    }
    public function read(array $file):string{
        self::assertDelivery((int)$file['size']);
        $p=$this->path($file['privateId']);
        if(!is_file($p)||is_link($p)||filesize($p)!==$file['size']||@hash_file('sha256',$p)!==$file['hash'])throw new RuntimeException('Die private Datei ist nicht verfügbar.',404);
        return 'data:'.$file['mime'].';base64,'.base64_encode((string)@file_get_contents($p));
    }
    public function clean(array $files):void{
        if(!is_dir($this->directory))return;
        $pending=[];
        $known=[];
        foreach($files as $f){
            $known[$f['privateId']]=true;
            if($f['status']==='pending')$pending[$f['privateId']]=true;
        }
        foreach(glob($this->directory.'/*.upload')?:[] as $p){
            $id=basename($p,'.upload');
            if(preg_match('/^[a-f0-9]{64}$/D',$id)&&!is_link($p)&&!isset($pending[$id])&&(isset($known[$id])||filemtime($p)<time()-86400))@unlink($p);
        }
    }
}
