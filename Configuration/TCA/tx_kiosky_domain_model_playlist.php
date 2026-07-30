<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_playlist', 'name', [
    'name' => T::input('Name', true),
    'public_uuid' => T::input('Public UUID', true),
    'description' => T::text('Description'),
    'orientation' => T::input('Orientation'),
    'slides' => [
        'label' => 'Slides',
        'config' => [
            'type' => 'group',
            'allowed' => 'tx_kiosky_domain_model_slide',
            'MM' => 'tx_kiosky_playlist_slide_mm',
            'multiple' => true,
            'size' => 10,
        ],
    ],
]);
