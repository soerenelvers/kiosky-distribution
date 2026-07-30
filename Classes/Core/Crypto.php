<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;

final class Crypto
{
    private const PREFIX = 'KIOSKY1';

    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new RuntimeException('Der CMS-Verschlüsselungsschlüssel ist nicht ausreichend konfiguriert.', 503);
        }
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Die PHP-OpenSSL-Erweiterung wird für sichere Zugangsdaten benötigt.', 503);
        }
    }

    /** @param array<string,mixed> $value */
    public function encrypt(array $value): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ciphertext = openssl_encrypt($json, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, self::PREFIX);
        if ($ciphertext === false) throw new RuntimeException('Zugangsdaten konnten nicht verschlüsselt werden.', 500);
        return base64_encode(self::PREFIX . $iv . $tag . $ciphertext);
    }

    /** @return array<string,mixed> */
    public function decrypt(string $encoded): array
    {
        $payload = base64_decode($encoded, true);
        if ($payload === false || !str_starts_with($payload, self::PREFIX) || strlen($payload) < strlen(self::PREFIX) + 28) {
            throw new RuntimeException('Der gespeicherte CrewBrain-Zugang ist beschädigt.', 422);
        }
        $offset = strlen(self::PREFIX);
        $iv = substr($payload, $offset, 12);
        $tag = substr($payload, $offset + 12, 16);
        $ciphertext = substr($payload, $offset + 28);
        $json = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, self::PREFIX);
        if ($json === false) {
            throw new RuntimeException('Der gespeicherte CrewBrain-Zugang kann mit dem aktuellen CMS-Schlüssel nicht gelesen werden.', 422);
        }
        try {
            $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Der gespeicherte CrewBrain-Zugang ist ungültig.', 422);
        }
        if (!is_array($value)) throw new RuntimeException('Der gespeicherte CrewBrain-Zugang ist ungültig.', 422);
        return $value;
    }

    private function key(): string
    {
        return hash('sha256', $this->secret, true);
    }
}
