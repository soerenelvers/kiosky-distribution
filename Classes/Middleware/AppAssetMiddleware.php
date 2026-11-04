<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Middleware;

use Kiosky\Core\PlayerLauncher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class AppAssetMiddleware implements MiddlewareInterface
{
    private const PREFIX = '/kiosky-assets/';

    /** @var array<string, string> */
    private const CONTENT_TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'wasm' => 'application/wasm',
        'md' => 'text/plain; charset=utf-8',
        'yaml' => 'application/yaml; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'zip' => 'application/zip',
        'gz' => 'application/gzip',
        'exe' => 'application/vnd.microsoft.portable-executable',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = '/' . ltrim($request->getUri()->getPath(), '/');
        $sitePath = $request->getAttribute('normalizedParams')?->getSitePath() ?? '/';
        if ($sitePath !== '/' && str_starts_with($path,$sitePath)) $path = '/' . ltrim(substr($path,strlen($sitePath)),'/');
        if (!str_starts_with($path, self::PREFIX)) {
            return $handler->handle($request);
        }
        if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)) {
            return new Response('php://temp', 405, ['Allow' => 'GET, HEAD']);
        }

        $relativePath = rawurldecode(substr($path, strlen(self::PREFIX)));
        if (!$this->isSafeRelativePath($relativePath)) {
            return new Response('php://temp', 404);
        }
        // Also retire files left behind by an older, manually overlaid installation.
        if (in_array(strtolower($relativePath), ['launchers/kiosky-player-windows-x86.exe', 'launchers/kiosky-player-windows-x86.url'], true)) {
            return new Response('php://temp', 404, ['Cache-Control'=>'no-store']);
        }

        // Old Player Center links must not keep distributing unconfigured templates.
        $legacyLaunchers = ['launchers/kiosky-player-windows-x64.exe'=>'windows-x64','launchers/kiosky-player-linux-x64'=>'linux-x64','launchers/kiosky-player-macos-universal'=>'macos-universal'];
        if (isset($legacyLaunchers[$relativePath])) {
            $base = $request->getUri()->getScheme().'://'.$request->getUri()->getAuthority().rtrim($sitePath,'/');
            try {
                $download = PlayerLauncher::download($legacyLaunchers[$relativePath],$base.'/kiosky-player-browser/',(string)GeneralUtility::getFileAbsFileName('EXT:kiosky/Resources/Public/App/launchers/'));
            } catch (\RuntimeException $error) { return new Response('php://temp',$error->getCode() ?: 503,['Cache-Control'=>'no-store']); }
            $response = new Response('php://temp',200,['Content-Type'=>$download['contentType'],'Content-Disposition'=>'attachment; filename="'.$download['filename'].'"','Content-Length'=>(string)strlen($download['body']),'Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff']);
            if (strtoupper($request->getMethod()) === 'GET') $response->getBody()->write($download['body']);
            return $response;
        }

        $specialFiles = [
            'display-player.js' => GeneralUtility::getFileAbsFileName(
                'EXT:kiosky/Resources/Public/JavaScript/DisplayPlayer.js',
            ),
        ];
        $appRoot = realpath((string)GeneralUtility::getFileAbsFileName('EXT:kiosky/Resources/Public/App/'));
        $candidate = $specialFiles[$relativePath] ?? ($appRoot !== false ? $appRoot . '/' . $relativePath : '');
        $file = $candidate !== '' ? realpath($candidate) : false;
        if ($file === false || !is_file($file) || !is_readable($file)) {
            return new Response('php://temp', 404);
        }
        if (!isset($specialFiles[$relativePath])
            && ($appRoot === false || !str_starts_with($file, $appRoot . DIRECTORY_SEPARATOR))) {
            return new Response('php://temp', 404);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $contentType = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';
        $response = new Response('php://temp', 200, [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Length' => (string)filesize($file),
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        if (strtoupper($request->getMethod()) === 'GET') {
            $response->getBody()->write((string)file_get_contents($file));
        }
        return $response;
    }

    private function isSafeRelativePath(string $path): bool
    {
        return $path !== ''
            && !str_contains($path, "\0")
            && !str_contains($path, '\\')
            && !str_starts_with($path, '/')
            && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) !== 1;
    }
}
