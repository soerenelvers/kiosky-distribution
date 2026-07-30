<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Middleware;

use Kiosky\Kiosky\Service\DisplayApiService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;

final readonly class DisplayApiMiddleware implements MiddlewareInterface
{
    public function __construct(private DisplayApiService $api) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = '/' . trim($request->getUri()->getPath(), '/');
        if (preg_match('#^/kiosky/display/([a-f0-9-]{36})$#i', $path, $match)) {
            return $this->playerDocument($request, $match[1]);
        }
        if (!preg_match('#^/kiosky/api/v1/displays/([a-f0-9-]{36})/(state|content|heartbeat|acknowledge|logs)$#i', $path, $match)) {
            return $handler->handle($request);
        }
        $token = $this->bearerToken($request);
        $display = $this->api->authenticate($match[1], $token);
        if ($display === null) {
            return $this->error('UNAUTHORIZED', 'Display-Anmeldung fehlgeschlagen.', 401);
        }
        $action = strtolower($match[2]);
        $method = strtoupper($request->getMethod());
        if (($action === 'state' || $action === 'content') && $method !== 'GET') {
            return $this->error('METHOD_NOT_ALLOWED', 'HTTP-Methode nicht erlaubt.', 405);
        }
        if (!in_array($action, ['state', 'content'], true) && $method !== 'POST') {
            return $this->error('METHOD_NOT_ALLOWED', 'HTTP-Methode nicht erlaubt.', 405);
        }
        $payload = $this->payload($request);
        return match ($action) {
            'state' => new JsonResponse($this->api->state($display), 200, $this->headers()),
            'content' => new JsonResponse($this->api->content($display), 200, $this->headers()),
            'heartbeat' => $this->handleHeartbeat($request, $display, $payload),
            'acknowledge' => new JsonResponse(
                ['data' => ['acknowledged' => $this->api->acknowledge($display, (string)($payload['commandUuid'] ?? ''))]],
                200,
                $this->headers(),
            ),
            // Player logs are accepted only as bounded structured messages. Persistent audit routing follows.
            'logs' => strlen((string)($payload['message'] ?? '')) <= 4096
                ? new JsonResponse(['data' => ['accepted' => true]], 202, $this->headers())
                : $this->error('PAYLOAD_TOO_LARGE', 'Logmeldung ist zu groß.', 413),
        };
    }

    private function playerDocument(ServerRequestInterface $request, string $uuid): ResponseInterface
    {
        $escapedUuid = htmlspecialchars($uuid, ENT_QUOTES);
        $scriptUri = htmlspecialchars(
            $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority()
                . '/kiosky-assets/display-player.js',
            ENT_QUOTES,
        );
        $html = '<!doctype html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Kiosky Display</title><style>html,body,#kiosky{width:100%;height:100%;margin:0;background:#000;color:#fff;overflow:hidden}'
            . '#status{position:fixed;inset:0;display:grid;place-items:center;font:16px system-ui}</style></head>'
            . '<body><main id="kiosky" data-display-uuid="' . $escapedUuid . '"><div id="status">Display wird verbunden …</div></main>'
            . '<script>window.KioskyDisplayRuntime={uuid:"' . $escapedUuid . '"};</script>'
            . '<script type="module" src="' . $scriptUri . '"></script></body></html>';
        return new HtmlResponse($html, 200, [
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; frame-src https://www.meteoblue.com; script-src 'self' 'unsafe-inline'; style-src 'unsafe-inline'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /** @param array<string, mixed> $display
     *  @param array<string, mixed> $payload
     */
    private function handleHeartbeat(ServerRequestInterface $request, array $display, array $payload): ResponseInterface
    {
        if (strlen((string)$request->getBody()) > 65536) {
            return $this->error('PAYLOAD_TOO_LARGE', 'Heartbeat ist zu groß.', 413);
        }
        $this->api->heartbeat($display, $payload, (string)($request->getServerParams()['REMOTE_ADDR'] ?? ''));
        return new JsonResponse(['data' => ['accepted' => true], 'meta' => ['serverTime' => time()]], 202, $this->headers());
    }

    /** @return array<string, mixed> */
    private function payload(ServerRequestInterface $request): array
    {
        $payload = json_decode((string)$request->getBody(), true);
        return is_array($payload) ? $payload : [];
    }

    private function bearerToken(ServerRequestInterface $request): string
    {
        $header = trim($request->getHeaderLine('Authorization'));
        return preg_match('/^Bearer\s+(.+)$/i', $header, $match) ? trim($match[1]) : trim($request->getHeaderLine('X-Display-Token'));
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status, $this->headers());
    }
}
