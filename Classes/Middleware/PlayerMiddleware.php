<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Middleware;

use Kiosky\Kiosky\Service\ApiFactory;
use Kiosky\Kiosky\Service\AppRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;

final readonly class PlayerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ApiFactory $apiFactory,
        private AppRenderer $renderer,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = '/' . trim($request->getUri()->getPath(), '/') . '/';
        $base = $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority();
        if ($path === '/kiosky-player/') {
            return new HtmlResponse($this->renderer->render('typo3', $base . '/kiosky-api/', $base . '/kiosky-player/', $request), 200, ['Cache-Control' => 'no-store']);
        }
        if ($path === '/kiosky-player-center/' || $path === '/player/') {
            return new HtmlResponse($this->renderer->renderPlayerPortal('index', $base . '/kiosky-api/', $base . '/kiosky-player/', $base . '/kiosky-player-browser/', $request), 200, ['Cache-Control' => 'no-store']);
        }
        if ($path === '/kiosky-player-browser/') {
            return new HtmlResponse($this->renderer->renderPlayerPortal('browser', $base . '/kiosky-api/', $base . '/kiosky-player/', $base . '/kiosky-player-browser/', $request), 200, ['Cache-Control' => 'no-store']);
        }
        if ($path !== '/kiosky-api/') return $handler->handle($request);
        $apiPath = (string)($request->getQueryParams()['path'] ?? '');
        $isPlayer = preg_match('#^/api/player/[^/]+/(state|heartbeat|proof)$#', $apiPath) === 1 || str_starts_with($apiPath, '/api/player-pairings');
        $isExternal = $apiPath === '/api/v1' || str_starts_with($apiPath, '/api/v1/');
        if (!$isPlayer && !$isExternal) {
            return new JsonResponse(['error' => ['code' => 'FORBIDDEN', 'message' => 'Öffentlich ist nur die Player-API verfügbar.']], 403);
        }
        if ($isExternal) {
            $configuredKey = trim((string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['kiosky']['externalApiKey'] ?? ''));
            $providedKey = trim($request->getHeaderLine('X-API-Key'));
            if ($configuredKey === '' || $providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
                return new JsonResponse(['error' => ['code' => 'FORBIDDEN', 'message' => 'Der externe API-Schlüssel fehlt oder ist ungültig.']], 403);
            }
        }
        $body = json_decode((string)$request->getBody(), true);
        $query = $request->getQueryParams();
        unset($query['path']);
        $api = $this->apiFactory->create($isExternal
            ? ['id' => 'typo3:external-api', 'name' => 'Externe API', 'email' => '', 'role' => 'admin']
            : ['id' => 'player', 'name' => 'Player', 'email' => '', 'role' => 'viewer']);
        [$status, $payload] = $api->handle($request->getMethod(), $apiPath, array_merge($query, is_array($body) ? $body : []), $request->getHeaderLine('X-Player-Key'));
        return new JsonResponse($payload, $status, ['Cache-Control' => 'no-store']);
    }
}
