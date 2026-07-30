<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_displaygroup', 'name', [
    'name' => T::input('Name', true),
    'public_uuid' => T::input('Public UUID', true),
    'parent' => T::relation('Parent group', 'tx_kiosky_domain_model_displaygroup'),
    'description' => T::text('Description'),
    'tags' => T::text('Tags'),
]);
