<?php

declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;

/** Root-provisioned trust; signed last-good grants survive transport failures. */
final class LicenseClient
{
    public const MODULES=['dashboard','events','channels','menus','advertising','media','displays','schedule','operations','integrations','easyjob','crewbrain','api','updates'];
    public function __construct(private readonly ?Crypto $crypto=null,private readonly string $platform='cms',private readonly string $version='unknown',private readonly ?\Closure $transport=null){}
    private static function setting(string $name): string{return (string)(defined($name)?constant($name):(getenv($name) ?: ''));}
    public static function managed(): bool{return self::setting('KIOSKY_LICENSE_SERVER_URL')!=='' || self::setting('KIOSKY_LICENSE_PUBLIC_KEY')!=='';}
    private static function authority(): array {
        $url=rtrim(self::setting('KIOSKY_LICENSE_SERVER_URL'),'/');$parts=parse_url($url);$key=str_replace('\\n',"\n",self::setting('KIOSKY_LICENSE_PUBLIC_KEY'));$parsed=openssl_pkey_get_public($key);$details=$parsed?openssl_pkey_get_details($parsed):false;
        if(!$parts || ($parts['scheme'] ?? '')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048)throw new RuntimeException('Lizenzserver und öffentlicher RSA-Prüfschlüssel müssen serverseitig gültig konfiguriert sein.',503);
        return [$url,$key];
    }
    public static function verify(array $lease,string $key,string $instanceId,?string $nonce=null): array {
        $payload=$lease['payload'] ?? '';$signature=$lease['signature'] ?? '';
        if(!is_string($payload)||strlen($payload)>30000||!is_string($signature)||openssl_verify($payload,self::decode($signature),$key,OPENSSL_ALGO_SHA256)!==1)throw new RuntimeException('Ungültige Lizenzsignatur.',422);
        $grant=json_decode(self::decode($payload),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($grant)||($grant['v'] ?? null)!==1||($grant['instanceId'] ?? '')!==$instanceId||!is_string($grant['licenseId'] ?? null)||!is_int($grant['revision'] ?? null)||$grant['revision']<1||!is_bool($grant['revoked'] ?? null)||!is_int($grant['issuedAt'] ?? null)||$grant['issuedAt']>microtime(true)*1000+300000||($nonce!==null&&($grant['nonce'] ?? '')!==$nonce)||!is_array($grant['modules'] ?? null)||!array_is_list($grant['modules'])||array_filter($grant['modules'],static fn($v):bool=>!is_string($v)||!in_array($v,self::MODULES,true))||count(array_unique($grant['modules']))!==count($grant['modules'])||($grant['revoked']&&$grant['modules']))throw new RuntimeException('Ungültige Lizenzfreigabe.',422);
        return $grant;
    }
    private static function decode(string $value): string{$v=base64_decode(strtr($value,'-_','+/'),true);if($v===false)throw new RuntimeException('Ungültige Lizenzdaten.',422);return $v;}
    private function stored(array $state): array {
        if(empty($state['licensingEncrypted']))return [];
        if(!$this->crypto)throw new RuntimeException('CMS-Verschlüsselung für Lizenzen fehlt.',503);
        return $this->crypto->decrypt($state['licensingEncrypted']);
    }
    public function initialize(array &$state): void {
        if(!$this->crypto)throw new RuntimeException('CMS-Verschlüsselung für Lizenzen fehlt.',503);
        if(empty($state['licensingEncrypted']))$this->write($state,['instanceId'=>bin2hex(random_bytes(16)),'instanceSecret'=>bin2hex(random_bytes(32)),'name'=>'Kiosky']);
    }
    private function write(array &$state,array $client): void{if(!$this->crypto)throw new RuntimeException('CMS-Verschlüsselung fehlt.',503);$state['licensingEncrypted']=$this->crypto->encrypt($client);}
    public function grants(array $state): array {
        if(!self::managed())return self::MODULES;
        try{$client=$this->stored($state);if(empty($client['lease']))return [];return self::verify($client['lease'],self::authority()[1],$client['instanceId'])['modules'];}catch(\Throwable){return [];}
    }
    public function allows(array $state,string ...$modules): bool{return !array_diff($modules,$this->grants($state));}
    public static function required(string $path): array {
        if(preg_match('#^/api/(auth|health|settings/licensing|settings/features|users|audit-logs|content-transfer)(/|$)#',$path)||str_starts_with($path,'/api/player/'))return [];
        if($path==='/api/v1'||str_starts_with($path,'/api/v1/'))return array_merge(['api'],self::required(preg_replace('#^/api/v1#','/api',$path)));
        foreach(['easyjob','crewbrain'] as $p)if(preg_match('#/'.$p.'(/|$)#',$path))return ['integrations',$p];
        $maps=['menus'=>'menus|menu-share|settings/(menus|menu-share)','advertising'=>'advertising|settings/advertising','channels'=>'slides|templates|channels|content-packages','displays'=>'displays|display-groups|groups|locations|matrix-displays|player-pairings','events'=>'events|public|settings/public-calendar','schedule'=>'schedule|schedules|schedule-target-order|assignments|content-assignments','operations'=>'presets|warnings|warning-templates|emergencies|dwd|operations','integrations'=>'integrations','api'=>'api-users','dashboard'=>'dashboard','media'=>'media','updates'=>'updates'];
        foreach($maps as $module=>$pattern)if(preg_match('#^/api/('.$pattern.')(/|$)#',$path))return [$module];return [];
    }
    public function assertPath(array $state,string $path): void{if(!$this->allows($state,...self::required($path)))throw new RuntimeException('Dieses Modul ist durch die Lizenz nicht freigeschaltet.',403);}
    public static function feature(string $key): ?string{return $key==='content'||str_starts_with($key,'editor_')?'channels':($key==='presets_warnings'?'operations':(in_array($key,self::MODULES,true)?$key:null));}
    public function features(array $state): array{$flags=$state['featureSettings'] ?? [];foreach(array_unique([...self::MODULES,'content','presets_warnings',...array_keys($flags)]) as $key){$module=self::feature($key);if($module&&!$this->allows($state,$module))$flags[$key]=false;}return $flags;}
    public function status(array $state): array {
        $client=$this->stored($state);$fingerprint='';try{$key=self::authority()[1];$der=base64_decode(preg_replace('/-----[^-]+-----|\s/','',$key));$fingerprint=hash('sha256',$der);}catch(\Throwable){}
        return ['mode'=>self::managed()?'managed':'staged','serverUrl'=>self::setting('KIOSKY_LICENSE_SERVER_URL'),'fingerprint'=>$fingerprint,'configured'=>$fingerprint!=='','masterAvailable'=>false,'instanceId'=>$client['instanceId'] ?? null,'name'=>$client['name'] ?? 'Kiosky','hasLicenseKey'=>!empty($client['licenseKey']),'modules'=>$this->grants($state),'catalog'=>self::MODULES,'lastSuccess'=>$client['lastSuccess'] ?? null,'lastAttempt'=>$client['lastAttempt'] ?? null,'error'=>$client['error'] ?? '', 'intervalMinutes'=>15,'offlinePolicy'=>'last-valid-indefinitely'];
    }
    public function synchronize(array &$state,array $input=[]): array {
        [$url,$publicKey]=self::authority();$this->initialize($state);$client=$this->stored($state);$key=$input['licenseKey'] ?? $client['licenseKey'] ?? '';$name=trim((string)($input['name'] ?? $client['name']));
        if(!is_string($key)||strlen($key)>200||!str_starts_with($key,'kiosky-license-')||$name===''||strlen($name)>120||preg_match('/[\x00-\x1f\x7f]/',$name))throw new RuntimeException('Ungültiger Lizenzschlüssel oder Instanzname.',422);
        $nonce=bin2hex(random_bytes(24));$request=['licenseKey'=>$key,'instanceId'=>$client['instanceId'],'instanceSecret'=>$client['instanceSecret'],'name'=>$name,'url'=>self::setting('KIOSKY_PUBLIC_URL') ?: 'Unbekannt','platform'=>$this->platform,'version'=>$this->version,'nonce'=>$nonce];
        try{
            $lease=$this->transport?($this->transport)($url,$request):self::download($url,$request);$grant=self::verify($lease,$publicKey,$client['instanceId'],$nonce);
            if(!empty($client['lease'])){try{$previous=self::verify($client['lease'],$publicKey,$client['instanceId']);}catch(\Throwable){$previous=null;}if($previous&&$previous['licenseId']===$grant['licenseId']&&($previous['revision']>$grant['revision']||$previous['issuedAt']>$grant['issuedAt']))throw new RuntimeException('Veraltete Lizenzantwort.',422);}
            $client=array_replace($client,['name'=>$name,'licenseKey'=>$key,'lease'=>$lease,'lastSuccess'=>(int)(microtime(true)*1000),'error'=>'']);
        }catch(\Throwable){$client['error']='Lizenzserver nicht erreichbar oder Antwort ungültig. Letzte bestätigte Freigabe bleibt erhalten.';}
        $client['lastAttempt']=(int)(microtime(true)*1000);$this->write($state,$client);return $this->status($state);
    }
    private static function download(string $url,array $input): array {
        if(!extension_loaded('curl'))throw new RuntimeException('Lizenzabgleich benötigt PHP cURL.',503);
        $ch=curl_init($url.'/api/license-server/lease');$data='';curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($input,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$data):int{if(strlen($data)+strlen($chunk)>64000)return 0;$data.=$chunk;return strlen($chunk);}]);$ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($ok===false||$status!==200)throw new RuntimeException('Lizenzabgleich fehlgeschlagen.',502);return json_decode($data,true,32,JSON_THROW_ON_ERROR);
    }
    public function runDue(array &$state): bool{if(!self::managed())return false;$client=$this->stored($state);if(empty($client['licenseKey'])||microtime(true)*1000-($client['lastAttempt'] ?? 0)<900000)return false;$this->synchronize($state);return true;}
    public function playback(array $state,array $value): array {
        if(!self::managed())return $value;$allowed=$this->grants($state);$enabled=static fn($slide):bool=>in_array(isset($slide['document']['menu'])||isset($slide['document']['menuPdf'])?'menus':(isset($slide['document']['advertisingConfigHash'])?'advertising':'channels'),$allowed,true);$blocked=!in_array('displays',$allowed,true);
        $slides=$blocked?[]:array_values(array_filter($value['slides'] ?? [],static fn($item):bool=>$enabled($item['slide'])));if(!in_array('events',$allowed,true))foreach($slides as &$item)$item['event']=null;unset($item);
        $revision=hash('sha256',json_encode([$value['contentRevision'] ?? '',$allowed]));$value=array_replace($value,['slides'=>$slides,'licenseBlocked'=>$blocked||(!empty($value['slides'])&&!$slides),'contentRevision'=>$revision]);
        if($blocked){foreach(['channel','directSlide','assignment','matrix'] as $key)$value[$key]=null;}elseif(!$enabled($value['directSlide'] ?? null))$value['directSlide']=null;
        if($blocked||!in_array('operations',$allowed,true))$value['warning']=$value['emergency']=null;
        if(isset($value['playbackSync']))$value['playbackSync']['key'].=':license:'.$revision;return $value;
    }
}
