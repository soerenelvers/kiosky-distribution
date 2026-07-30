<?php

declare(strict_types=1);

use Kiosky\Kiosky\Controller\ModuleController;

return [
    'web_kiosky' => [
        'parent' => 'content',
        'position' => ['after' => 'web_layout'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/web/kiosky',
        'labels' => [
            'title' => 'Kiosky',
            'description' => 'Veranstaltungen, Displays und Digital Signage verwalten',
            'shortDescription' => 'Kiosky',
        ],
        'iconIdentifier' => 'module-kiosky',
        'routes' => [
            '_default' => [
                'target' => ModuleController::class . '::handleRequest',
            ],
        ],
    ],
];
