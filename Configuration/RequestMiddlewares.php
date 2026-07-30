<?php

use Kiosky\Kiosky\Middleware\PlayerMiddleware;
use Kiosky\Kiosky\Middleware\DisplayApiMiddleware;

return [
    'frontend' => [
        'kiosky/display-api' => [
            'target' => DisplayApiMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
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
