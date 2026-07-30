<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;

return T::create('tx_kiosky_domain_model_display', 'name', [
    'name' => T::input('Name', true),
    'identifier' => T::input('Internal identifier', true),
    'public_uuid' => T::input('Public UUID', true),
    'display_group' => T::relation('Display group', 'tx_kiosky_domain_model_displaygroup'),
    'location' => T::relation('Location', 'tx_kiosky_domain_model_location'),
    'room' => T::input('Room'),
    'address' => T::text('Address'),
    'width' => T::integer('Width', 1920),
    'height' => T::integer('Height', 1080),
    'orientation' => T::input('Orientation'),
    'default_channel' => T::relation('Default channel', 'tx_kiosky_domain_model_channel'),
    'default_playlist' => T::relation('Default playlist', 'tx_kiosky_domain_model_playlist'),
    'default_background' => T::file('Default background', ['jpg', 'jpeg', 'png', 'webp', 'svg']),
    'status' => T::input('Status'),
    'description' => T::text('Description'),
    'tags' => T::text('Tags'),
]);
