<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

final class DisplayTokenService
{
    /** @return array{token:string, hash:string} */
    public function generate(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = password_hash($token, PASSWORD_ARGON2ID);
        if ($hash === false) {
            throw new \RuntimeException('Display-Token konnte nicht sicher gehasht werden.');
        }
        return ['token' => $token, 'hash' => $hash];
    }
}
