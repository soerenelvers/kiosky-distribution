<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;
require_once __DIR__.'/FontStorage.php';
require_once __DIR__.'/MediaUpload.php';

/** Password-encrypted, identity-preserving Kiosky moves. CMS authentication/licensing stay local. */
final class SystemMigration
{
    public const FORMAT='kiosky-system-migration';
    public const LIMIT=128000000;
    public const COLLECTIONS=['events','eventSchedules','slides','slideVersions','templates','channels','locations','groups','displays','matrices','schedules','assignments','presets','warningTemplates','warnings','presetTriggers','presetTriggerRuns','mediaFolders','mediaAssets'];
    public const SETTINGS=['featureSettings','scheduleTargetOrder','eventEditorSettings','eventTemplates','publicCalendarSettings','advertisingSettings','menuSettings','menuShare','navigationOrder','titleExclusions','fonts'];
    public const EXTRA=['users','apiUsers','menuAccess','contentChannelAccess','menuSubscriptions','dwd','presetHandoffs','crewbrain','easyjob','activeEventSource','externalSources','trash'];
    private const AAD='kiosky-system-migration:1';
    private static function password(mixed $value):string{if(!is_string($value)||strlen($value)<12||strlen($value)>256)throw new RuntimeException('Das Paketpasswort muss 12 bis 256 UTF-8-Bytes lang sein.',422);return $value;}
    private static function bytes(mixed $value,?int $length=null):string
    {
        if(!is_string($value)||$value==='')throw new RuntimeException('Ungültiges verschlüsseltes Umzugspaket.',422);
        $decoded=base64_decode($value,true);if($decoded===false||base64_encode($decoded)!==$value||($length!==null&&strlen($decoded)!==$length))throw new RuntimeException('Ungültiges verschlüsseltes Umzugspaket.',422);return $decoded;
    }
    private static function memory(int $bytes):void
    {
        $raw=trim((string)ini_get('memory_limit'));if($raw===''||$raw==='-1')return;$limit=(int)$raw;$suffix=strtolower(substr($raw,-1));$limit*=match($suffix){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
        if(memory_get_usage(true)+$bytes*4+8000000>$limit)throw new RuntimeException('Der PHP-Arbeitsspeicher reicht für dieses Umzugspaket nicht aus. memory_limit erhöhen und erneut prüfen; es wurden keine Zieldaten geändert.',413);
    }
    /** Serialize native file creation and its rollback across concurrent imports. */
    public static function lock():mixed
    {
        $directory=sys_get_temp_dir().'/kiosky-migration-'.hash('sha256',__DIR__.':'.($_SERVER['DOCUMENT_ROOT'] ?? '').':'.getmyuid());
        if(is_link($directory)||(!is_dir($directory)&&!@mkdir($directory,0700,true)&&!is_dir($directory)))throw new RuntimeException('Der Umzug kann nicht sicher gesperrt werden.',503);
        $file=$directory.'/import.lock';if(is_link($file))throw new RuntimeException('Die Umzugssperre ist ungültig.',503);$lock=@fopen($file,'c');
        if($lock===false)throw new RuntimeException('Die Umzugssperre ist nicht verfügbar.',503);@chmod($file,0600);
        if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);throw new RuntimeException('Es läuft bereits ein Systemumzug. Bitte nach dessen Abschluss erneut prüfen.',423);}return $lock;
    }
    public static function seal(array $payload,mixed $passphrase):array
    {
        $password=self::password($passphrase);self::validate($payload);$plain=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if(strlen($plain)>self::LIMIT)throw new RuntimeException('Das Umzugspaket überschreitet 128 MB Nutzdaten.',413);self::memory(strlen($plain));
        $salt=random_bytes(16);$iv=random_bytes(12);$key=hash_pbkdf2('sha256',$password,$salt,310000,32,true);$tag='';$data=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,self::AAD);
        if($data===false)throw new RuntimeException('Das Umzugspaket konnte nicht verschlüsselt werden.',500);
        return ['format'=>self::FORMAT,'version'=>1,'kdf'=>'PBKDF2-SHA256','iterations'=>310000,'cipher'=>'AES-256-GCM','salt'=>base64_encode($salt),'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($data)];
    }
    public static function open(array $package,mixed $passphrase):array
    {
        $password=self::password($passphrase);
        if(($package['format'] ?? '')!==self::FORMAT||($package['version'] ?? null)!==1||($package['kdf'] ?? '')!=='PBKDF2-SHA256'||($package['iterations'] ?? null)!==310000||($package['cipher'] ?? '')!=='AES-256-GCM'||!is_string($package['data'] ?? null)||strlen($package['data'])>ceil(self::LIMIT/3)*4)throw new RuntimeException('Nicht unterstütztes oder zu großes Umzugspaket.',422);
        self::memory((int)(strlen($package['data'])*.75));$salt=self::bytes($package['salt'] ?? null,16);$iv=self::bytes($package['iv'] ?? null,12);$tag=self::bytes($package['tag'] ?? null,16);$data=self::bytes($package['data']);
        $key=hash_pbkdf2('sha256',$password,$salt,310000,32,true);$plain=openssl_decrypt($data,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,self::AAD);
        if($plain===false)throw new RuntimeException('Das Paketpasswort ist falsch oder das Umzugspaket ist beschädigt.',422);
        try{$payload=json_decode($plain,true,80,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new RuntimeException('Das Umzugspaket ist beschädigt.',422);}
        if(!is_array($payload))throw new RuntimeException('Ungültiger Umzugsinhalt.',422);self::validate($payload);return $payload;
    }
    public static function validate(array $payload):void
    {
        if(($payload['schema'] ?? null)!==1||(!is_string($payload['id'] ?? null)||!preg_match('/^[a-f0-9-]{16,64}$/D',$payload['id']))||!in_array($payload['source']['platform'] ?? '',['standalone','wordpress','typo3'],true)||!is_array($payload['state'] ?? null))throw new RuntimeException('Ungültiger Umzugsinhalt.',422);
        $nodes=0;$visit=static function(mixed $value,int $depth=0)use(&$visit,&$nodes):void{if(++$nodes>1000000||$depth>64)throw new RuntimeException('Das Umzugspaket ist zu komplex.',422);if(is_array($value))foreach($value as $key=>$entry){if(in_array($key,['__proto__','constructor','prototype'],true))throw new RuntimeException('Ungültiger Eigenschaftsname im Umzugspaket.',422);$visit($entry,$depth+1);}};$visit($payload);
        $state=$payload['state'];$ids=[];
        foreach([...self::COLLECTIONS,'users','apiUsers'] as $name){$records=$state[$name] ?? null;if(!is_array($records)||!array_is_list($records)||count($records)>10000)throw new RuntimeException('Ungültige Sammlung: '.$name,422);$ids[$name]=[];foreach($records as $record){$id=$record['id'] ?? null;if(!is_string($id)||$id===''||strlen($id)>200||preg_match('/[\x00-\x1f]/',$id)||isset($ids[$name][$id]))throw new RuntimeException('Ungültige oder doppelte Kennung: '.$name,422);$ids[$name][$id]=true;}}
        foreach([['slides','eventId','events'],['channels','eventId','events'],['eventSchedules','eventId','events'],['slideVersions','slideId','slides'],['displays','defaultSlideId','slides'],['displays','defaultChannelId','channels'],['displays','locationId','locations'],['matrices','defaultChannelId','channels'],['mediaAssets','folderId','mediaFolders'],['mediaFolders','parentId','mediaFolders'],['presetTriggers','presetId','presets']] as [$collection,$key,$target])foreach($state[$collection] as $row)if(!empty($row[$key])&&!isset($ids[$target][$row[$key]]))throw new RuntimeException('Fehlende Verknüpfung: '.$collection.'.'.$key,422);
        foreach($state['channels'] as $channel)foreach($channel['items'] ?? [] as $item)if(!empty($item['slideId'])&&!isset($ids['slides'][$item['slideId']]))throw new RuntimeException('Ein Kanal enthält eine fehlende Slide.',422);
        foreach($state['groups'] as $group)foreach($group['displayIds'] ?? [] as $id)if(!isset($ids['displays'][$id]))throw new RuntimeException('Eine Displaygruppe enthält ein fehlendes Display.',422);
        foreach($state['matrices'] as $matrix)foreach($matrix['members'] ?? [] as $member)if(!isset($ids['displays'][$member['displayId'] ?? '']))throw new RuntimeException('Eine Matrix enthält ein fehlendes Display.',422);
        foreach($state['mediaAssets'] as $asset){if(!is_string($asset['src'] ?? null))throw new RuntimeException('Eine Mediendatei fehlt.',422);if(str_starts_with($asset['src'],'data:')){$comma=strpos($asset['src'],',');$bytes=base64_decode(substr($asset['src'],$comma===false?0:$comma+1),true);if($bytes===false||($asset['sha256'] ?? '')!==hash('sha256',$bytes))throw new RuntimeException('Eine Mediendatei ist beschädigt.',422);MediaUpload::decode($asset,self::LIMIT);}else{$url=parse_url($asset['src']);if(!$url||!in_array($url['scheme'] ?? '',['http','https'],true)||empty($url['host'])||isset($url['user'])||isset($url['pass']))throw new RuntimeException('Nicht eingebettete lokale Mediendatei oder ungültiger externer Link.',422);}}
        foreach($payload['fontFiles'] ?? [] as $entry){$font=FontStorage::validate(self::bytes($entry['data'] ?? null));if($font['id']!==($entry['id'] ?? ''))throw new RuntimeException('Eine Schriftdatei ist beschädigt.',422);}
        $fontIds=array_column($payload['fontFiles'] ?? [],'id');if(count($fontIds)>20||count(array_unique($fontIds))!==count($fontIds)||count($state['fonts'] ?? [])!==count($fontIds))throw new RuntimeException('Die Schriftdateien sind unvollständig.',422);foreach($state['fonts'] ?? [] as $font)if(!in_array($font['id'] ?? '',$fontIds,true))throw new RuntimeException('Eine Schriftdatei fehlt.',422);
        $folders=array_column($state['mediaFolders'],null,'id');foreach($folders as $folder){$seen=[];$id=$folder['id'];while($id){if(isset($seen[$id]))throw new RuntimeException('Die Medienordner enthalten einen Kreis.',422);$seen[$id]=true;$id=$folders[$id]['parentId'] ?? null;}}
    }
    public static function snapshot(array $source,string $platform,string $version,array $users,bool $credentials,?callable $assetData=null,?callable $fontData=null):array
    {
        $state=array_intersect_key($source,array_flip([...self::COLLECTIONS,...self::SETTINGS,...self::EXTRA]));
        foreach(self::COLLECTIONS as $key)$state[$key]=array_values($state[$key] ?? []);
        $slideIds=array_flip(array_column($state['slides'],'id'));$versionCount=count($state['slideVersions']);$state['slideVersions']=array_values(array_filter($state['slideVersions'],static fn(array $version):bool=>isset($slideIds[$version['slideId'] ?? ''])));$cleanup=['orphanSlideVersions'=>$versionCount-count($state['slideVersions'])];
        $profiles=[];foreach([...($source['portableUsers'] ?? []),...($source['migrationUsers'] ?? []),...$users] as $user)if(is_string($user['id'] ?? null))$profiles[$user['id']]=array_intersect_key(array_replace($user,['displayName'=>$user['displayName'] ?? $user['name'] ?? $user['username'] ?? $user['email'] ?? 'Benutzer']),array_flip(['id','username','email','displayName','name','role','status','allLocations','locationIds']));
        $state['users']=array_values($profiles);$state['apiUsers']=array_values($source['apiUsers'] ?? []);
        foreach(self::SETTINGS as $key)$state[$key]??=in_array($key,['fonts','eventTemplates','navigationOrder','titleExclusions'],true)?[]:[];
        foreach($state['channels'] as &$channel){$channel['items']=array_values(array_filter($channel['items'] ?? [],static fn(array $item):bool=>empty($item['advertisingInterstitial'])&&!str_starts_with((string)($item['slideId'] ?? ''),'advertising-event:')));foreach($channel['items'] as &$item)unset($item['slide']);unset($item);if(!empty($channel['playlists'])){$channel['playlists'][0]['items']=$channel['items'];$channel['playlists'][0]['orientation']=$channel['orientation'] ?? ($channel['playlists'][0]['orientation'] ?? 'auto');}}unset($channel);
        // Move Kiosky-managed files and every referenced native file, not unrelated website attachments.
        $references=[];$collect=static function(mixed $value)use(&$collect,&$references):void{if(is_array($value)){foreach($value as $entry)$collect($entry);}elseif(is_string($value))$references[$value]=true;};$collect(array_diff_key($state,array_flip(['mediaAssets','mediaFolders','fonts'])));
        $fontAssets=array_flip(array_filter(array_column($state['fonts'],'assetId')));
        $state['mediaAssets']=array_values(array_filter($state['mediaAssets'],static fn(array $asset):bool=>!isset($fontAssets[$asset['id']])&&(!preg_match('/^(wp:|fal:)/',$asset['id'])||isset($references[$asset['id']])||isset($references[$asset['src'] ?? ''])||!empty($asset['metadata']['kioskyManaged'])||!empty($asset['metadata']['legacyId']))));
        $size=0;foreach($state['mediaAssets'] as &$asset){$src=$asset['src'] ?? '';if(!str_starts_with($src,'data:')&&preg_match('/^(wp:|fal:)/',$asset['id'])){if(!$assetData)throw new RuntimeException('Die native Mediendatei kann nicht exportiert werden: '.($asset['name'] ?? $asset['id']),503);$asset['sourceUrl']=$src;$asset['sourceUrls']=[$src];$asset['src']=$assetData($asset);}
            if(str_starts_with($asset['src'],'data:')){$data=base64_decode(substr($asset['src'],strpos($asset['src'],',')+1),true);if($data===false)throw new RuntimeException('Eine Mediendatei ist ungültig.',422);$asset['sha256']=hash('sha256',$data);}
            $size+=strlen($asset['src']);if($size>self::LIMIT)throw new RuntimeException('Die Medien überschreiten die Paketgrenze von 128 MB. Es wurde keine unvollständige Datei erzeugt.',413);self::memory($size);
        }unset($asset);
        $fontFiles=[];foreach($state['fonts'] as &$font){if(!$fontData)throw new RuntimeException('Die private Schriftablage ist nicht verfügbar.',503);$file=$fontData($font['id']);$fontFiles[]=['id'=>$font['id'],'data'=>$file['data']];unset($font['assetId']);}unset($font);
        if(!empty($state['menuShare']['passwordHash']))$state['menuShare']['passwordAlgorithm']??='pbkdf2-sha256-600000-32';
        if(!$credentials){foreach($state['displays'] as &$display)unset($display['playerKey']);unset($display);foreach($state['apiUsers'] as &$user){unset($user['tokenHash']);$user['status']='revoked';}unset($user);$state['menuShare']=array_replace((array)$state['menuShare'],['enabled'=>false,'passwordHash'=>'','salt'=>'','secret'=>'']);}
        foreach(['crewbrain','easyjob'] as $provider){$state[$provider]??=['config'=>[],'mapping'=>[]];unset($state[$provider]['syncLogs']);unset($state[$provider]['config']['encryptedCredential'],$state[$provider]['config']['encryptedCredentials']);if(!$credentials){unset($state[$provider]['config']['credential'],$state[$provider]['config']['credentials']);$state[$provider]['config']['syncEnabled']=false;}}
        return ['schema'=>1,'id'=>bin2hex(random_bytes(16)),'exportedAt'=>gmdate('c'),'source'=>['platform'=>$platform,'version'=>$version],'includeCredentials'=>$credentials,'state'=>$state,'fontFiles'=>$fontFiles,'userCredentials'=>[],'cleanup'=>$cleanup];
    }
    public static function digest(array $state):string
    {
        $data=array_intersect_key($state,array_flip([...self::COLLECTIONS,...self::SETTINGS,...self::EXTRA]));$clean=static function(mixed $value)use(&$clean):mixed{if(!is_array($value))return $value;$result=[];foreach($value as $key=>$entry)if(!in_array($key,['createdAt','updatedAt','lastSeenAt','lastUsedAt','online','cached','playerVersion'],true))$result[$key]=$clean($entry);return $result;};$data=$clean($data);
        $data['apiUsers']=array_map(static function(array $row):array{unset($row['lastUsedAt'],$row['updatedAt']);return $row;},$state['apiUsers'] ?? []);unset($data['users']);return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
    public static function sourceDigest(array $payload):string{return hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));}
    public static function preview(array $state,array $payload,array $users=[]):array
    {
        self::validate($payload);if(!empty($payload['state']['menuShare']['enabled'])&&($payload['state']['menuShare']['passwordAlgorithm'] ?? '')==='scrypt-16384-8-1-64'&&!function_exists('sodium_crypto_pwhash_scryptsalsa208sha256'))throw new RuntimeException('Für das importierte Freigabepasswort wird PHP Sodium benötigt.',503);$counts=[];$target=[];foreach(self::COLLECTIONS as $name){$counts[$name]=count($payload['state'][$name]);$target[$name]=count($state[$name] ?? []);}
        return ['id'=>$payload['id'],'source'=>$payload['source'],'counts'=>$counts,'targetCounts'=>$target,'sourceDigest'=>self::sourceDigest($payload),'targetDigest'=>self::digest($state),'alreadyImported'=>($state['migrationReceipt']['id'] ?? '')===$payload['id']&&($state['migrationReceipt']['sourceDigest'] ?? '')===self::sourceDigest($payload),'users'=>$payload['state']['users'],'targetUsers'=>$users,'notices'=>[...(!empty($payload['cleanup']['orphanSlideVersions'])?[$payload['cleanup']['orphanSlideVersions'].' historische Versionsstände endgültig gelöschter Slides wurden beim Export ausgelassen.']:[]),'Die Zielanmeldung und Lizenzidentität bleiben erhalten.','Native CMS-Benutzer werden ausdrücklich zugeordnet. Nicht zugeordnete Eigentümer bleiben gesperrt. Standortbeschränkte Quellkonten bleiben bis zur gesonderten Rechtevergabe unzugeordnet.','Bei neuer Serveradresse sind Player-URLs beziehungsweise DNS/Weiterleitung anzupassen.','Bestehende native Medien bleiben erhalten; importierte Dateien werden neu in der Mediathek angelegt.']];
    }
    /** Pure state stage. Caller validates/writes font bytes and encrypts provider credentials before saving. */
    public static function stage(array &$target,array $payload,array $options,array $users=[]):array
    {
        $preview=self::preview($target,$payload,$users);if($preview['alreadyImported'])return ['id'=>$payload['id'],'counts'=>$preview['counts'],'alreadyImported'=>true];
        if(($options['confirmation'] ?? '')!=='KIOSKY UMZIEHEN'||($options['targetDigest'] ?? '')!==$preview['targetDigest']||($options['sourceDigest'] ?? '')!==$preview['sourceDigest'])throw new RuntimeException('Die Zielinstanz wurde geändert oder die Umzugsbestätigung fehlt.',409);
        foreach($preview['targetCounts'] as $key=>$count)if(!in_array($key,['warningTemplates','mediaAssets','mediaFolders'],true)&&$count>0&&($options['replaceExisting'] ?? false)!==true)throw new RuntimeException('Die Zielinstanz enthält Kiosky-Daten. Ersetzen muss ausdrücklich bestätigt werden.',409);
        $state=$payload['state'];$map=[];$targetIds=array_flip(array_column($users,'id'));foreach($state['users'] as $user){$chosen=$options['userMap'][$user['id']] ?? null;if($chosen!==null&&$chosen!==''){if(!isset($targetIds[$chosen]))throw new RuntimeException('Eine Benutzerzuordnung ist nicht mehr gültig.',422);if(($user['allLocations'] ?? true)===false)throw new RuntimeException('Standortbeschränkte Quellbenutzer müssen zunächst ohne CMS-Kontozuordnung übernommen werden; native Rechte anschließend separat einrichten.',422);$map[$user['id']]=$chosen;}else $map[$user['id']]=str_starts_with($user['id'],'unmapped:')?$user['id']:'unmapped:'.substr(hash('sha256',$user['id']),0,24);}
        $owner=static fn(mixed $id):mixed=>is_string($id)?($map[$id] ?? 'unmapped:'.substr(hash('sha256',$id),0,24)):$id;
        foreach($state['slides'] as &$slide)if(isset($slide['ownerId']))$slide['ownerId']=$owner($slide['ownerId']);unset($slide);
        $grants=[];foreach($state['contentChannelAccess'] ?? [] as $id=>$channels)$grants[$owner((string)$id)]=array_values(array_unique([...($grants[$owner((string)$id)] ?? []),...$channels]));$state['contentChannelAccess']=$grants;
        $grants=[];foreach($state['menuAccess'] ?? [] as $id=>$access){$key=$owner((string)$id);$grants[$key]=['enabled'=>!empty($access['enabled']),'slideIds'=>array_values(array_unique([...($grants[$key]['slideIds'] ?? []),...($access['slideIds'] ?? [])]))];}$state['menuAccess']=$grants;
        foreach($state['displays'] as &$display){$display['playerKey']??=bin2hex(random_bytes(24));foreach(['online','lastSeenAt','currentChannelId','currentSlideId','currentChannelName','currentSlideName','cached','version','playerVersion'] as $key)unset($display[$key]);}unset($display);
        $revoked=[];foreach($target['apiUsers'] ?? [] as $previous)if(($previous['status'] ?? '')==='revoked')$revoked[$previous['tokenHash'] ?? '']=true;
        foreach($state['apiUsers'] as &$user)if(isset($revoked[$user['tokenHash'] ?? '']))$user['status']='revoked';unset($user);
        $sourceApiIds=array_flip(array_column($state['apiUsers'],'id'));foreach($target['apiUsers'] ?? [] as $previous)if(!isset($sourceApiIds[$previous['id']])){$previous['status']='revoked';$previous['revokedAt']=gmdate('c');$state['apiUsers'][]=$previous;}
        foreach($state['apiUsers'] as &$user)if(empty($user['tokenHash'])){$user['tokenHash']=hash('sha256',random_bytes(32));$user['status']='revoked';}unset($user);
        $state['migrationUsers']=array_map(static fn(array $user):array=>array_replace($user,['id'=>$owner($user['id']),'sourceUserId'=>$user['id'],'status'=>isset($targetIds[$owner($user['id'])])?($user['status'] ?? 'active'):'disabled']),$state['users']);unset($state['users']);
        // Native file IDs belong to the target CMS. Namespace incoming IDs before any file API sees them.
        $replacements=[];foreach(['mediaFolders','mediaAssets'] as $name)foreach($state[$name] as $record)$replacements[$record['id']]='move:'.$payload['id'].':'.substr(hash('sha256',$record['id']),0,32);
        $aliases=[];foreach($state['mediaAssets'] as $asset)foreach($asset['sourceUrls'] ?? [$asset['sourceUrl'] ?? null] as $url)if(is_string($url)&&$url!=='')$aliases[$url]='kiosky-migration-media:'.$replacements[$asset['id']];
        $rewrite=static function(mixed $value)use(&$rewrite,$replacements,$aliases):mixed{if(is_array($value))return array_map($rewrite,$value);if(is_string($value))return $replacements[$value] ?? $aliases[$value] ?? $value;return $value;};$state=$rewrite($state);
        $state['mediaFolders']=[...($target['mediaFolders'] ?? []),...$state['mediaFolders']];$state['mediaAssets']=[...($target['mediaAssets'] ?? []),...$state['mediaAssets']];
        $state['migrationMediaAliases']=[];foreach($payload['state']['mediaAssets'] as $asset)if(!empty($asset['sourceUrl'])||!empty($asset['sourceUrls']))$state['migrationMediaAliases']['kiosky-migration-media:'.$replacements[$asset['id']]]=$replacements[$asset['id']];
        foreach([...self::COLLECTIONS,...self::SETTINGS,...self::EXTRA,'migrationUsers','migrationMediaAliases'] as $name)if(array_key_exists($name,$state))$target[$name]=$state[$name];
        unset($target['users']);$target['portableUsers']=$state['migrationUsers'];$target['commands']=[];$target['deliveredPlayerCommands']=[];$target['playerPairings']=[];$target['proof']=[];$target['presetExecutions']=[];$target['playbackRevisions']=[];$target['menuShareAttempts']=[];
        $target['migrationReceipt']=['sourceDigest'=>$preview['sourceDigest'],'id'=>$payload['id'],'counts'=>$preview['counts'],'importedAt'=>gmdate('c'),'notices'=>$preview['notices']];return $target['migrationReceipt'];
    }
}
