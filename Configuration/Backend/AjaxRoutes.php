<?php

use Kiosky\Kiosky\Controller\ApiController;

return [
    'kiosky_api' => [
        'path' => '/kiosky/api',
        'target' => ApiController::class . '::handleRequest',
        'inheritAccessFromModule' => 'web_kiosky',
    ],
];
