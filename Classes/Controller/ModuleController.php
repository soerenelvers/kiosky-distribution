<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Controller;

use Kiosky\Kiosky\Security\PermissionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;

#[AsController]
final readonly class ModuleController
{
    /** @var array<string, string> */
    private const TABLES = [
        'displays' => 'tx_kiosky_domain_model_display',
        'display-groups' => 'tx_kiosky_domain_model_displaygroup',
        'locations' => 'tx_kiosky_domain_model_location',
        'channels' => 'tx_kiosky_domain_model_channel',
        'playlists' => 'tx_kiosky_domain_model_playlist',
        'slides' => 'tx_kiosky_domain_model_slide',
        'templates' => 'tx_kiosky_domain_model_template',
        'events' => 'tx_kiosky_domain_model_event',
        'presets' => 'tx_kiosky_domain_model_preset',
        'schedules' => 'tx_kiosky_domain_model_schedule',
    ];

    public function __construct(
        private UriBuilder $uriBuilder,
        private ConnectionPool $connectionPool,
        private PermissionService $permissions,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->permissions->require(PermissionService::VIEW);
        $section = $this->section($request);
        $pid = max(0, (int)($request->getQueryParams()['id'] ?? 0));
        if (isset(self::TABLES[$section])) {
            return new HtmlResponse($this->recordSection($section, self::TABLES[$section], $pid));
        }
        return new HtmlResponse($this->dashboard());
    }

    private function dashboard(): string
    {
        $onlineThreshold = time() - 120;
        $counts = [
            'Displays' => $this->count('tx_kiosky_domain_model_display'),
            'Online' => $this->count('tx_kiosky_domain_model_display', 'last_heartbeat', $onlineThreshold, true),
            'Offline' => $this->count('tx_kiosky_domain_model_display', 'last_heartbeat', $onlineThreshold, false),
            'Channels' => $this->count('tx_kiosky_domain_model_channel'),
            'Events' => $this->count('tx_kiosky_domain_model_event'),
            'Active presets' => $this->count('tx_kiosky_domain_model_preset', 'active', 1, true),
            'Schedules' => $this->count('tx_kiosky_domain_model_schedule'),
        ];
        $cards = '';
        foreach ($counts as $label => $count) {
            $cards .= '<div class="card p-3"><div class="h1">' . $count . '</div><div>' . htmlspecialchars($label) . '</div></div>';
        }
        return $this->frame('Kiosky overview', '<div class="row row-cols-1 row-cols-md-4 g-3">' . $cards . '</div>'
            . '<div class="callout callout-info mt-4"><div class="callout-body">Kiosky runs entirely from TYPO3 records, FAL assets, backend users and TYPO3 routes.</div></div>');
    }

    private function recordSection(string $section, string $table, int $pid): string
    {
        $listUri = (string)$this->uriBuilder->buildUriFromRoute('web_list', ['id' => $pid, 'table' => $table]);
        $newUri = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [$table => [$pid => 'new']],
            'returnUrl' => $listUri,
        ]);
        $write = $this->permissions->isGranted($section === 'events'
            ? PermissionService::MANAGE_EVENTS
            : ($section === 'displays' ? PermissionService::MANAGE_DISPLAYS : PermissionService::MANAGE_CONTENT));
        $actions = '<a class="btn btn-default" href="' . htmlspecialchars($listUri, ENT_QUOTES) . '">Open record list</a>';
        if ($write) {
            $actions .= ' <a class="btn btn-primary" href="' . htmlspecialchars($newUri, ENT_QUOTES) . '">Create record</a>';
        }
        return $this->frame(ucwords(str_replace('-', ' ', $section)), '<div class="mb-3">' . $actions . '</div>'
            . '<p>Records are edited through TYPO3 FormEngine and saved through DataHandler. File fields use the TYPO3 file browser and FAL references.</p>');
    }

    private function count(string $table, ?string $field = null, int $value = 0, bool $greaterOrEqual = true): int
    {
        $query = $this->connectionPool->getQueryBuilderForTable($table);
        $constraints = [$query->expr()->eq('deleted', 0)];
        if ($field !== null) {
            $constraints[] = $greaterOrEqual
                ? $query->expr()->gte($field, $query->createNamedParameter($value))
                : $query->expr()->lt($field, $query->createNamedParameter($value));
        }
        return (int)$query->count('uid')->from($table)->where(...$constraints)->executeQuery()->fetchOne();
    }

    private function section(ServerRequestInterface $request): string
    {
        $path = trim($request->getUri()->getPath(), '/');
        return basename($path);
    }

    private function frame(string $title, string $content): string
    {
        return '<div class="container-fluid"><h1>' . htmlspecialchars($title) . '</h1>' . $content . '</div>';
    }
}
