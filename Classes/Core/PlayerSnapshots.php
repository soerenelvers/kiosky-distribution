<?php
declare(strict_types=1);
namespace Kiosky\Core;
/** One private, expiring picture per player, outside CMS public media and state options. */
final class PlayerSnapshots
{
    public function __construct(private readonly string $directory){}
    public static function data(mixed $value):string{
        if(!is_string($value)||strlen($value)>2800000||!preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#D',$value,$m))throw new \RuntimeException('Ungültiges Bildschirmfoto.',422);
        $bytes=base64_decode($m[1],true);$size=$bytes===false?false:@getimagesizefromstring($bytes);
        if($bytes===false||strlen($bytes)>2097152||base64_encode($bytes)!==$m[1]||!$size||$size[2]!==IMAGETYPE_PNG||$size[0]>1920||$size[1]>1920)throw new \RuntimeException('Ungültiges Bildschirmfoto oder zu große Auflösung.',422);return $value;
    }
    private function path(string $id):string{if(!preg_match('/^[a-f0-9-]{32,36}$/D',$id))throw new \RuntimeException('Player nicht gefunden.',404);return $this->directory.'/'.$id.'.json';}
    public function get(string $id):array{
        $file=$this->path($id);if(!is_file($file))return ['pending'=>false];$result=json_decode((string)file_get_contents($file),true);if(!is_array($result)||strtotime($result['capturedAt']??'1970-01-01')<time()-600){@unlink($file);return ['pending'=>false];}return $result;
    }
    public function receive(string $id,array $request,array $body):array{
        if(($request['id']??null)!==($body['id']??null)||($request['expiresAt']??0)<=microtime(true)*1000)throw new \RuntimeException('Die Fotoanforderung ist abgelaufen.',409);
        $result=['id'=>$request['id'],'capturedAt'=>gmdate('c'),'unsupported'=>($body['unsupported']??false)===true,'data'=>($body['unsupported']??false)===true?null:self::data($body['data']??null)];
        if(!is_dir($this->directory)&&!mkdir($this->directory,0700,true)&&!is_dir($this->directory))throw new \RuntimeException('Bildschirmfoto konnte nicht gespeichert werden.',503);
        $file=$this->path($id);$lock=fopen($file.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Bildschirmfoto konnte nicht gesperrt werden.',503);
        try{if(($this->get($id)['id']??'')===$request['id'])throw new \RuntimeException('Fotoanforderung bereits beantwortet.',409);$temporary=tempnam($this->directory,'.capture-');if($temporary===false)throw new \RuntimeException('Bildschirmfoto konnte nicht gespeichert werden.',503);chmod($temporary,0600);if(file_put_contents($temporary,json_encode($result,JSON_THROW_ON_ERROR))===false||!rename($temporary,$file)){@unlink($temporary);throw new \RuntimeException('Bildschirmfoto konnte nicht gespeichert werden.',503);}}
        finally{flock($lock,LOCK_UN);fclose($lock);}return $result;
    }
}
