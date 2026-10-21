<?php

declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;

/** A password-protected, content-only capability, independent of CMS accounts. */
final class MenuShare
{
    private const LIFETIME = 28800;
    public static function publicConfig(array $state): array
    {
        $record=$state['menuShare'] ?? [];
        return ['enabled'=>!empty($record['enabled']),'shareId'=>$record['shareId'] ?? '', 'hasPassword'=>!empty($record['passwordHash']),'slideIds'=>$record['slideIds'] ?? [],'moduleEnabled'=>($state['featureSettings']['menus'] ?? true)!==false];
    }
    public static function save(array &$state,array $input): array
    {
        if (array_diff(array_keys($input),['enabled','password','slideIds','resetLink']) || !is_bool($input['enabled'] ?? null) || !is_array($input['slideIds'] ?? null) || !array_is_list($input['slideIds'])) throw new RuntimeException('Ungültige Speisekarten-Freigabe.',422);
        $live=array_column(array_filter($state['slides'] ?? [],static fn(array $slide): bool => empty($slide['deletedAt']) && ($slide['status'] ?? '')!=='archived' && (isset($slide['document']['menu']) || isset($slide['document']['menuPdf']))),'id');
        foreach ($input['slideIds'] as $id) if (!is_string($id) || !in_array($id,$live,true)) throw new RuntimeException('Eine freigegebene Karte wurde nicht gefunden.',422);
        $record=$state['menuShare'] ?? [];
        if (isset($input['password']) && $input['password']!=='') {
            if (!is_string($input['password']) || preg_match_all('/./us',$input['password'])<12 || preg_match_all('/./us',$input['password'])>256 || strlen($input['password'])>1024) throw new RuntimeException('Das Freigabepasswort muss mindestens 12 Zeichen lang sein.',422);
            $record['salt']=bin2hex(random_bytes(16));
            $record['passwordHash']=hash_pbkdf2('sha256',$input['password'],$record['salt'],600000,64);
        }
        if ($input['enabled'] && empty($record['passwordHash'])) throw new RuntimeException('Bitte zuerst ein Freigabepasswort setzen.',422);
        if ($input['enabled'] && !$input['slideIds']) throw new RuntimeException('Bitte mindestens eine Karte freigeben.',422);
        $record['enabled']=$input['enabled'];$record['slideIds']=array_values(array_unique($input['slideIds']));
        if (empty($record['shareId']) || ($input['resetLink'] ?? false)===true) $record['shareId']=bin2hex(random_bytes(24));
        $record['secret']=bin2hex(random_bytes(32));$state['menuShare']=$record;
        return self::publicConfig($state);
    }
    private static function active(array $state,?string $shareId=null): array
    {
        if (($state['featureSettings']['menus'] ?? true)===false) throw new RuntimeException('Das Speisekarten-Modul ist ausgeschaltet.',503);
        $record=$state['menuShare'] ?? [];
        if (empty($record['enabled']) || empty($record['passwordHash']) || empty($record['secret']) || ($shareId!==null && $shareId!==($record['shareId'] ?? ''))) throw new RuntimeException('Die Speisekarten-Freigabe ist nicht verfügbar.',404);
        return $record;
    }
    /** @return array{0:int,1:array,2:bool} */
    public static function login(array &$state,array $input,string $address): array
    {
        $record=self::active($state,is_string($input['shareId'] ?? null)?$input['shareId']:'');
        $key=hash('sha256',$address);$attempts=$state['menuShareAttempts'] ?? [];
        foreach ($attempts as $id=>$value) if ($value['until']<=time()) unset($attempts[$id]);
        $previous=$attempts[$key] ?? null;
        if ($previous && $previous['count']>=5) throw new RuntimeException('Zu viele Versuche. Bitte nach fünf Minuten erneut versuchen.',429);
        $password=is_string($input['password'] ?? null) && strlen($input['password'])<=1024?$input['password']:'';
        $hash=hash_pbkdf2('sha256',$password,$record['salt'],600000,64);
        if (!hash_equals($record['passwordHash'],$hash)) {
            $attempts[$key]=['count'=>($previous['count'] ?? 0)+1,'until'=>$previous['until'] ?? time()+300];
            $state['menuShareAttempts']=array_slice($attempts,-256,null,true);
            return [401,['error'=>['code'=>'MENU_SHARE_PASSWORD_INVALID','message'=>'Das Freigabepasswort ist ungültig.']],true];
        }
        $expires=time()+self::LIFETIME;
        $payload=rtrim(strtr(base64_encode(json_encode(['id'=>$record['shareId'],'expires'=>$expires*1000,'nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR)),'+/','-_'),'=');
        return [200,['token'=>'ms1.'.$payload.'.'.hash_hmac('sha256',$payload,$record['secret']),'expiresAt'=>gmdate('c',$expires)],false];
    }
    public static function authorize(array $state,string $token): array
    {
        $record=self::active($state);
        $parts=explode('.',$token);
        if (strlen($token)>2048 || count($parts)!==3 || $parts[0]!=='ms1' || !preg_match('/^[a-f0-9]{64}$/',$parts[2])) throw new RuntimeException('Bitte das Freigabepasswort erneut eingeben.',401);
        if (!hash_equals(hash_hmac('sha256',$parts[1],$record['secret']),$parts[2])) throw new RuntimeException('Die Speisekarten-Sitzung ist ungültig.',401);
        $decoded=base64_decode(strtr($parts[1],'-_','+/'),true);
        $payload=$decoded!==false?json_decode($decoded,true):null;
        if (!is_array($payload) || ($payload['id'] ?? null)!==$record['shareId'] || !is_numeric($payload['expires'] ?? null) || $payload['expires']<=time()*1000 || $payload['expires']>(time()+self::LIFETIME)*1000) throw new RuntimeException('Die Speisekarten-Sitzung ist abgelaufen.',401);
        return $record['slideIds'];
    }
}
