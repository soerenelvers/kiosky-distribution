<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_channel', 'name', [
    'name' => T::input('Name', true),
    'public_uuid' => T::input('Public UUID', true),
    'description' => T::text('Description'),
    'priority' => T::integer('Priority'),
    'playlist' => T::relation('Playlist', 'tx_kiosky_domain_model_playlist'),
]);
