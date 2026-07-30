<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_template', 'name', [
    'name' => T::input('Name', true),
    'public_uuid' => T::input('Public UUID', true),
    'description' => T::text('Description'),
    'category' => T::input('Category'),
    'preview' => T::file('Preview', ['jpg', 'jpeg', 'png', 'webp']),
    'width' => T::integer('Width', 1920),
    'height' => T::integer('Height', 1080),
    'orientation' => T::input('Orientation'),
    'definition_json' => T::text('Template definition'),
]);
