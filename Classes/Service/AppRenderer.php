<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

final readonly class AppRenderer
{
    public function render(
        string $platform,
        string $apiEndpoint,
        string $playerBaseUrl,
        ServerRequestInterface $request,
        string $nonce = '',
    ): string
    {
        $indexPath = GeneralUtility::getFileAbsFileName('EXT:kiosky/Resources/Public/App/index.html');
        if (!$indexPath || !is_readable($indexPath)) throw new RuntimeException('Die Kiosky-Oberfläche fehlt in der Extension.');
        $assetBase = $this->assetBaseUrl($request);
        $meta = $this->meta('kiosky-platform', $platform)
            . $this->meta('kiosky-api-endpoint', $apiEndpoint)
            . $this->meta('kiosky-asset-base-url', rtrim($assetBase, '/'))
            . $this->meta('kiosky-player-base-url', $playerBaseUrl)
            . $this->meta('kiosky-player-center-url', rtrim(dirname(rtrim($playerBaseUrl, '/')), '/') . '/kiosky-player-center/')
            . ($nonce !== '' ? $this->meta('kiosky-request-header-typo3-nonce', $nonce) : '');
        $html = $this->rewriteAppAssetUrls((string)file_get_contents($indexPath), $assetBase);
        return str_replace('<head>', '<head>' . $meta, $html);
    }

    public function renderPlayerPortal(
        string $page,
        string $apiEndpoint,
        string $playerBaseUrl,
        string $browserUrl,
        ServerRequestInterface $request,
    ): string
    {
        $appPath = GeneralUtility::getFileAbsFileName('EXT:kiosky/Resources/Public/App/');
        $assetBase = $this->assetBaseUrl($request);
        $file = $appPath . 'player/' . ($page === 'browser' ? 'browser.html' : 'index.html');
        if (!is_readable($file)) throw new RuntimeException('Das Kiosky Player Center fehlt in der Extension.');
        $html = str_replace('/player/', $assetBase . 'player/', (string)file_get_contents($file));
        $runtime = $page === 'browser'
            ? ['apiEndpoint' => $apiEndpoint, 'playerBaseUrl' => $playerBaseUrl, 'browserUrl' => $browserUrl]
            : ['browserUrl' => $browserUrl, 'downloads' => [
                'windows-x64' => $assetBase . 'launchers/kiosky-player-windows-x64.exe',
                'windows-x86' => $assetBase . 'launchers/kiosky-player-windows-x86.exe',
                'linux-x64' => $assetBase . 'launchers/kiosky-player-linux-x64',
                'macos-universal' => $assetBase . 'launchers/kiosky-player-macos-universal',
            ]];
        $name = $page === 'browser' ? 'KioskyPlayerRuntime' : 'KioskyPlayerCenterRuntime';
        return str_replace('</head>', '<script>window.' . $name . '=' . json_encode($runtime, JSON_UNESCAPED_SLASHES) . ';</script></head>', $html);
    }

    private function assetBaseUrl(ServerRequestInterface $request): string
    {
        $webPath = PathUtility::getPublicResourceWebPath('EXT:kiosky/Resources/Public/App/runtime-config.js');
        $uri = $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority()
            . '/' . ltrim($webPath, '/');
        $lastSlash = strrpos($uri, '/');
        if ($lastSlash === false) {
            throw new RuntimeException('Die öffentliche URL der Kiosky-Oberfläche konnte nicht ermittelt werden.');
        }
        return substr($uri, 0, $lastSlash + 1);
    }

    private function rewriteAppAssetUrls(string $html, string $assetBase): string
    {
        $assetBase = htmlspecialchars($assetBase, ENT_QUOTES);
        $html = str_replace(
            ['href="styles.css', 'src="runtime-config.js', 'src="app.js'],
            [
                'href="' . $assetBase . 'styles.css',
                'src="' . $assetBase . 'runtime-config.js',
                'src="' . $assetBase . 'app.js',
            ],
            $html,
            $replacementCount,
        );
        if ($replacementCount !== 3) {
            throw new RuntimeException('Die Asset-Verweise der Kiosky-Oberfläche konnten nicht vollständig aufgelöst werden.');
        }
        return $html;
    }

    private function meta(string $name, string $content): string
    {
        return '<meta name="' . htmlspecialchars($name, ENT_QUOTES) . '" content="' . htmlspecialchars($content, ENT_QUOTES) . '">';
    }
}
