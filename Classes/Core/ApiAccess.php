<?php

declare(strict_types=1);
namespace Kiosky\Core;

use RuntimeException;

/** CMS equivalent of the standalone API-user repository. Never stores plaintext keys. */
final class ApiAccess
{
    public static function token(string $authorization, string $apiKey): string
    {
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $match)) return strlen($match[1]) <= 512 ? $match[1] : '';
        return strlen($apiKey) <= 512 ? trim($apiKey) : '';
    }

    public static function publicUser(array $user): array
    {
        return array_intersect_key($user, array_flip(['id','name','description','tokenPrefix','status','lastUsedAt','revokedAt','createdAt','updatedAt']));
    }

    public static function create(array &$state, array $input, string $createdBy): array
    {
        if (!is_string($input['name'] ?? null) || (isset($input['description']) && !is_string($input['description']))) throw new RuntimeException('Name und Beschreibung müssen Text enthalten.',422);
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $length = static fn(string $text): int => function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length($name) < 2 || $length($name) > 100) throw new RuntimeException('Der Name des API-Benutzers muss zwischen 2 und 100 Zeichen lang sein.',422);
        if ($length($description) > 500) throw new RuntimeException('Die Beschreibung darf höchstens 500 Zeichen lang sein.',422);
        $token = 'kiosky_'.bin2hex(random_bytes(8)).'_'.rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        $now = gmdate('c');
        $user = ['id'=>bin2hex(random_bytes(16)),'name'=>$name,'description'=>$description,'tokenPrefix'=>substr($token,0,24).'…','tokenHash'=>hash('sha256',$token),'status'=>'active','createdBy'=>$createdBy,'createdAt'=>$now,'updatedAt'=>$now];
        $state['apiUsers'][] = $user;
        return ['apiUser'=>self::publicUser($user),'token'=>$token];
    }

    /** Revocation is monotonic, even when another request saves an older application snapshot. */
    public static function mergeUsers(array $incoming, array $current): array
    {
        $users = array_column($current,null,'id');
        foreach ($incoming as $user) {
            $id = $user['id'];
            $latest = $users[$id] ?? null;
            if ($latest) {
                if (($latest['status'] ?? '') === 'revoked') {
                    $user['status'] = 'revoked';
                    $user['revokedAt'] = $latest['revokedAt'] ?? $latest['updatedAt'];
                }
                foreach (['lastUsedAt','updatedAt'] as $field) {
                    if (isset($latest[$field]) && (!isset($user[$field]) || strtotime($latest[$field]) > strtotime($user[$field]))) $user[$field] = $latest[$field];
                }
            }
            $users[$id] = $user;
        }
        return array_values($users);
    }

    public static function authenticate(array &$state, string $token): ?array
    {
        if (!str_starts_with($token,'kiosky_') || strlen($token) > 512) return null;
        $hash = hash('sha256',$token);
        foreach ($state['apiUsers'] ?? [] as $index => $user) {
            if (($user['status'] ?? '') !== 'active' || !hash_equals((string)($user['tokenHash'] ?? ''),$hash)) continue;
            // Avoid rewriting the shared CMS state on every high-frequency status poll.
            if (empty($user['lastUsedAt']) || strtotime($user['lastUsedAt']) <= time() - 60) $user['lastUsedAt'] = $user['updatedAt'] = gmdate('c');
            $state['apiUsers'][$index] = $user;
            return self::publicUser($user);
        }
        return null;
    }
}
