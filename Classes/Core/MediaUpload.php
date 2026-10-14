<?php

declare(strict_types=1);
namespace Kiosky\Core;

use RuntimeException;

final class MediaUpload
{
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme']??'', ['http','https'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) throw new RuntimeException('Ungültige Medien-URL.',422);
        $host = trim($parts['host'],'[]');
        $ips = filter_var($host,FILTER_VALIDATE_IP) ? [$host] : array_merge(array_column(dns_get_record($host,DNS_A) ?: [],'ip'),array_column(dns_get_record($host,DNS_AAAA) ?: [],'ipv6'));
        if (!$ips) throw new RuntimeException('Medienserver konnte nicht aufgelöst werden.',422);
        foreach ($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new RuntimeException('Medienimporte aus privaten Netzwerken sind nicht erlaubt.',422);
    }

    public static function decode(array $asset): array
    {
        $src = (string)($asset['src'] ?? '');
        if (!preg_match('#^data:([^;,]+);base64,(.+)$#s', $src, $match)) {
            throw new RuntimeException('Bitte eine Datei hochladen oder ein vorhandenes CMS-Medium auswählen.', 422);
        }
        $bytes = base64_decode($match[2], true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > 20_000_000) {
            throw new RuntimeException('Ungültige Mediendatei oder Datei größer als 20 MB.', 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/avif'=>'avif','video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/wav'=>'wav','application/pdf'=>'pdf'];
        if ($match[1] === 'image/svg+xml' || preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<svg\b/i', $bytes)) {
            $mime = 'image/svg+xml';
            $extensions[$mime] = 'svg';
            if (preg_match('/<!DOCTYPE|<!ENTITY|<\?(?!xml\s)/i', $bytes)) throw new RuntimeException('Unsicheres SVG.',422);
            $xml = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try { $valid = $xml->loadXML($bytes, LIBXML_NONET); }
            finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
            if (!$valid || $xml->documentElement?->localName !== 'svg') throw new RuntimeException('Ungültiges SVG.',422);
            foreach ($xml->getElementsByTagName('*') as $node) {
                if (!in_array($node->localName, ['svg','g','path','rect','circle','ellipse','line','polyline','polygon','text','tspan','defs','linearGradient','radialGradient','stop','clipPath','mask','pattern','symbol','use','title','desc'], true)) throw new RuntimeException('SVG enthält nicht unterstützte aktive Inhalte.',422);
                foreach ($node->attributes as $attribute) {
                    if ($attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') continue;
                    if ($attribute->localName === 'style' || str_starts_with(strtolower($attribute->localName),'on') || preg_match('/javascript:|data:|https?:|expression\(|@import/i',$attribute->value) || ($attribute->localName==='href' && !str_starts_with($attribute->value,'#'))) throw new RuntimeException('SVG enthält aktive oder externe Inhalte.',422);
                    preg_match_all('/url\((.*?)\)/i', $attribute->value, $urls);
                    foreach ($urls[1] as $url) if (!str_starts_with(trim($url, " \t\n\r\0\x0B\"'"),'#')) throw new RuntimeException('SVG enthält externe Verweise.',422);
                }
            }
        }
        if (!isset($extensions[$mime])) throw new RuntimeException('Dieses Dateiformat wird von der CMS-Medienverwaltung nicht unterstützt.', 422);
        $name = basename(str_replace('\\', '/', (string)($asset['name'] ?? 'Medium')));
        // Never trust a client-supplied extension or MIME type.
        $name = (pathinfo($name, PATHINFO_FILENAME) ?: 'Medium') . '.' . $extensions[$mime];
        return ['bytes'=>$bytes, 'mime'=>$mime, 'name'=>$name];
    }
}
