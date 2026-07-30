<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Controller;

use Kiosky\Kiosky\Service\ApiFactory;
use Kiosky\Kiosky\Security\PermissionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;

#[AsController]
final readonly class ApiController
{
    public function __construct(
        private ApiFactory $apiFactory,
        private PermissionService $permissions,
    )
    {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $backendUser = $GLOBALS['BE_USER'];
        $body = json_decode((string)$request->getBody(), true);
        $configuredRole = (string)($backendUser->user['tx_kiosky_role'] ?? '');
        $role = in_array($configuredRole, ['administrator', 'manager', 'editor', 'event_editor', 'display_operator', 'viewer', 'admin'], true)
            ? $configuredRole
            : ($backendUser->isAdmin() ? 'administrator' : 'viewer');
        if (!$this->permissions->isGranted(PermissionService::VIEW, $backendUser)) {
            return new JsonResponse(['error' => ['code' => 'FORBIDDEN', 'message' => 'Kiosky-Zugriff verweigert.']], 403);
        }
        $apiRole = match ($role) {
            'administrator', 'admin', 'manager' => 'admin',
            'editor', 'event_editor', 'display_operator' => 'editor',
            default => 'viewer',
        };
        $identity = [
            'id' => 'typo3:' . (string)$backendUser->user['uid'],
            'name' => (string)($backendUser->user['realName'] ?: $backendUser->user['username']),
            'email' => (string)($backendUser->user['email'] ?: $backendUser->user['username']),
            'role' => $apiRole,
        ];
        $apiPath = (string)($request->getQueryParams()['path'] ?? '');
        if ((str_starts_with($apiPath, '/api/integrations/') || str_starts_with($apiPath, '/api/v1'))
            && !$this->permissions->isGranted(PermissionService::MANAGE_SETTINGS, $backendUser)) {
            return new JsonResponse(['error' => ['code' => 'FORBIDDEN', 'message' => 'Schnittstellen dürfen nur von TYPO3-Administratoren verwaltet werden.']], 403);
        }
        $api = $this->apiFactory->create($identity);
        $query = $request->getQueryParams();
        unset($query['path']);
        [$status, $payload] = $api->handle(
            $request->getMethod(),
            $apiPath,
            array_merge($query, is_array($body) ? $body : []),
            (string)($request->getHeaderLine('X-Player-Key'))
        );
        return new JsonResponse($payload, $status, ['Cache-Control' => 'no-store']);
    }
}
