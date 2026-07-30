<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Controller;

use Kiosky\Kiosky\Security\PermissionService;
use Kiosky\Kiosky\Service\AppRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\HtmlResponse;

#[AsController]
final readonly class ModuleController
{
    public function __construct(
        private UriBuilder $uriBuilder,
        private AppRenderer $renderer,
        private PermissionService $permissions,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->permissions->require(PermissionService::VIEW);
        $apiEndpoint = (string)$this->uriBuilder->buildUriFromRoute('ajax_kiosky_api');
        $baseUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority();
        $html = $this->renderer->render(
            'typo3',
            $apiEndpoint,
            $baseUrl . '/kiosky-player/',
            $request,
        );

        return new HtmlResponse($html, 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}
