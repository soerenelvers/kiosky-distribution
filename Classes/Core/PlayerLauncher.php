<?php
declare(strict_types=1);
namespace Kiosky\Core;

use RuntimeException;

/** Server-bound player downloads. Native executables are templates, never downloads. */
final class PlayerLauncher
{
    private const MARKER = 'KIOSKY_PLAYER_URL_PLACEHOLDER';
    private const CAPACITY = 4096;
    private const PLATFORMS = [
        'windows-x64' => ['kiosky-player-windows-x64.exe', '.exe', 'application/vnd.microsoft.portable-executable'],
        'linux-x64' => ['kiosky-player-linux-x64', '', 'application/octet-stream'],
        'linux-mint-x64' => ['kiosky-player-linux-x64', '', 'application/octet-stream'],
        'macos-universal' => ['kiosky-player-macos-universal', '.app.zip', 'application/zip'],
    ];

    /** @return array{body:string,filename:string,contentType:string} */
    public static function download(string $platform, string $playerUrl, string $templateRoot): array
    {
        if (!isset(self::PLATFORMS[$platform])) throw new RuntimeException('Diese Player-Variante ist nicht verfügbar.', 404);
        $url = parse_url($playerUrl);
        if (!$url || !in_array($url['scheme'] ?? '', ['https', 'http'], true) || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || preg_match('/[\x00-\x20\x7f"<>]/', $playerUrl)
            || str_contains($playerUrl, self::MARKER) || strlen($playerUrl) >= self::CAPACITY) {
            throw new RuntimeException('Die Player-Adresse ist ungültig oder zu lang.', 422);
        }
        [$template, $extension, $type] = self::PLATFORMS[$platform];
        $path = rtrim($templateRoot, '/\\').'/'.$template;
        if (!is_readable($path)) throw new RuntimeException('Die Player-Vorlage fehlt im Paket.', 503);
        $body = @file_get_contents($path);
        if ($body === false) throw new RuntimeException('Die Player-Vorlage kann nicht gelesen werden.', 503);
        $offset = 0; $replacements = 0;
        while (($position = strpos($body, self::MARKER, $offset)) !== false) {
            if ($position + self::CAPACITY > strlen($body)) throw new RuntimeException('Die Player-Vorlage ist beschädigt.', 503);
            $body = substr_replace($body, str_pad($playerUrl, self::CAPACITY, "\0"), $position, self::CAPACITY);
            $offset = $position + self::CAPACITY; $replacements++;
        }
        if (!$replacements) throw new RuntimeException('Die Player-Vorlage ist ungültig.', 503);
        if ($platform === 'macos-universal') $body = self::macApp($body);
        return ['body'=>$body, 'filename'=>'Kiosky-Universal-Player-'.$platform.$extension, 'contentType'=>$type];
    }

    private static function macApp(string $binary): string
    {
        $app = 'Kiosky Display Player.app/'; $contents = $app.'Contents/';
        $info = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">'
            .'<plist version="1.0"><dict><key>CFBundleExecutable</key><string>Kiosky Display Player</string>'
            .'<key>CFBundleIdentifier</key><string>de.kiosky.display-player</string>'
            .'<key>CFBundleName</key><string>Kiosky Display Player</string>'
            .'<key>CFBundleDisplayName</key><string>Kiosky Display Player</string>'
            .'<key>CFBundlePackageType</key><string>APPL</string><key>CFBundleShortVersionString</key><string>1.0</string>'
            .'<key>LSMinimumSystemVersion</key><string>11.0</string><key>NSHighResolutionCapable</key><true/></dict></plist>';
        return self::zip([
            [$app, '', 040755], [$contents, '', 040755], [$contents.'MacOS/', '', 040755],
            [$contents.'Info.plist', $info, 0100644], [$contents.'PkgInfo', 'APPL????', 0100644],
            [$contents.'MacOS/Kiosky Display Player', $binary, 0100755],
        ]);
    }

    /** Stored ZIP, including executable mode; no optional PHP ZIP extension needed. */
    private static function zip(array $entries): string
    {
        $local = ''; $central = '';
        foreach ($entries as [$name, $data, $mode]) {
            $size = strlen($data); $crc = crc32($data); $length = strlen($name); $offset = strlen($local);
            $local .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, 0, 33, $crc, $size, $size, $length, 0).$name.$data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 0x0314, 20, 0x0800, 0, 0, 33, $crc, $size, $size, $length, 0, 0, 0, 0, $mode << 16, $offset).$name;
        }
        return $local.$central.pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($local), 0);
    }
}
