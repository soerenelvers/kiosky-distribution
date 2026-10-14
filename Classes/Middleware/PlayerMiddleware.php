<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Middleware;

use Kiosky\Core\ApiAccess;
use Kiosky\Core\ExternalRequest;
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
        $sitePath = $request->getAttribute('normalizedParams')?->getSitePath() ?? '/';
        $uriPath = $request->getUri()->getPath();
        if ($sitePath !== '/' && str_starts_with($uriPath,$sitePath)) $uriPath = substr($uriPath,strlen($sitePath));
        $path = '/' . trim($uriPath, '/') . '/';
        $base = $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority() . rtrim($sitePath,'/');
        if ($path === '/kiosky-player/') {
            return new HtmlResponse($this->renderer->render('typo3', $base . '/kiosky-api/', $base . '/kiosky-player/', $request), 200, ['Cache-Control' => 'no-store']);
        }
        if ($path === '/kiosky-player-center/' || $path === '/player/') {
            return new HtmlResponse($this->renderer->renderPlayerPortal('index', $base . '/kiosky-api/', $base . '/kiosky-player/', $base . '/kiosky-player-browser/', $request), 200, ['Cache-Control' => 'no-store']);
        }
        if ($path === '/kiosky-player-browser/') {
            return new HtmlResponse($this->renderer->renderPlayerPortal('browser', $base . '/kiosky-api/', $base . '/kiosky-player/', $base . '/kiosky-player-browser/', $request), 200, ['Cache-Control' => 'no-store']);
        }
        $directExternal = preg_match('#^/kiosky-api/v1(?:/|$)#', $path) === 1;
        if ($path !== '/kiosky-api/' && !$directExternal) return $handler->handle($request);
        $apiPath = $directExternal ? '/api/v1' . rtrim(substr($path,strlen('/kiosky-api/v1')),'/') : (string)($request->getQueryParams()['path'] ?? '');
        $isPlayer = preg_match('#^/api/player/[^/]+/(state|heartbeat|proof)$#', $apiPath) === 1 || str_starts_with($apiPath, '/api/player-pairings');
        $isExternal = $apiPath === '/api/v1' || str_starts_with($apiPath, '/api/v1/');
        if (!$isPlayer && !$isExternal) {
            return new JsonResponse(['error' => ['code' => 'FORBIDDEN', 'message' => 'Öffentlich ist nur die Player-API verfügbar.']], 403);
        }
        if ($isExternal) {
            $requestId = bin2hex(random_bytes(16));
            try {
                $body = ExternalRequest::body((string)$request->getBody());
                $query = $request->getQueryParams();
                unset($query['path']);
                $token = ApiAccess::token($request->getHeaderLine('Authorization'),$request->getHeaderLine('X-API-Key'));
                $api = $this->apiFactory->create(['id'=>'external','name'=>'Externe API','email'=>'','role'=>'viewer'],true);
                $legacyKey = trim((string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['kiosky']['externalApiKey'] ?? ''));
                [$status,$payload] = $api->handleExternal($request->getMethod(),$apiPath,array_merge($query,$body),$token,$legacyKey,$requestId,(string)($request->getServerParams()['REMOTE_ADDR'] ?? ''));
                if ($apiPath === '/api/v1' && $status === 200) {
                    $payload['documentation'] = $base.'/kiosky-assets/docs/external-api.md';
                    $payload['openapi'] = $base.'/kiosky-assets/docs/openapi.yaml';
                }
            } catch (\RuntimeException $error) {
                $status = $error->getCode() ?: 400;
                $payload = ExternalRequest::error($error);
            }
            return new JsonResponse($payload,$status,ExternalRequest::headers($requestId,$status));
        }
        $body = json_decode((string)$request->getBody(), true);
        $query = $request->getQueryParams();
        unset($query['path']);
        $api = $this->apiFactory->create(['id' => 'player', 'name' => 'Player', 'email' => '', 'role' => 'viewer']);
        [$status, $payload] = $api->handle($request->getMethod(), $apiPath, array_merge($query, is_array($body) ? $body : []), $request->getHeaderLine('X-Player-Key'));
        return new JsonResponse($payload, $status, ['Cache-Control' => 'no-store']);
    }
}
