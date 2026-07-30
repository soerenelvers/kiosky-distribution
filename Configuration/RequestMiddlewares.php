<?php

use Kiosky\Kiosky\Middleware\AppAssetMiddleware;
use Kiosky\Kiosky\Middleware\PlayerMiddleware;
use Kiosky\Kiosky\Middleware\DisplayApiMiddleware;

return [
    'frontend' => [
        'kiosky/app-assets' => [
            'target' => AppAssetMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
        'kiosky/display-api' => [
            'target' => DisplayApiMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
            ],
            'after' => [
                'kiosky/app-assets',
            ],
        ],
        'kiosky/player' => [
            'target' => PlayerMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
            ],
            'after' => [
                'kiosky/display-api',
            ],
        ],
    ],
];
