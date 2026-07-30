<?php

use Kiosky\Kiosky\Controller\ModuleController;

$modules = [
    'kiosky' => [
        'position' => ['after' => 'web'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/kiosky',
        'labels' => 'LLL:EXT:kiosky/Resources/Private/Language/locallang_mod.xlf',
        'icon' => 'EXT:kiosky/Resources/Public/Icons/Extension.svg',
    ],
];

$sections = [
    'overview' => 'Overview',
    'displays' => 'Displays',
    'display-groups' => 'Display groups',
    'locations' => 'Locations',
    'channels' => 'Channels',
    'playlists' => 'Playlists',
    'slides' => 'Slides',
    'templates' => 'Templates',
    'events' => 'Events',
    'presets' => 'Presets',
    'schedules' => 'Schedules',
    'media' => 'Media',
    'status' => 'System status',
    'logs' => 'Logs',
    'settings' => 'Settings',
];

foreach ($sections as $section => $title) {
    $key = 'kiosky_' . str_replace('-', '_', $section);
    $modules[$key] = [
        'parent' => 'kiosky',
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/kiosky/' . $section,
        'labels' => 'LLL:EXT:kiosky/Resources/Private/Language/locallang_mod.xlf',
        'icon' => 'EXT:kiosky/Resources/Public/Icons/Extension.svg',
        'routes' => [
            '_default' => [
                'target' => ModuleController::class . '::handleRequest',
            ],
        ],
    ];
}

return $modules;
