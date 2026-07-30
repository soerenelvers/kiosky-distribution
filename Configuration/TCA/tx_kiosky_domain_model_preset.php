<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_preset', 'name', [
    'name' => T::input('Name', true),
    'public_uuid' => T::input('Public UUID', true),
    'description' => T::text('Description'),
    'priority' => T::integer('Priority'),
    'target_displays' => T::text('Target displays'),
    'target_groups' => T::text('Target groups'),
    'channel' => T::relation('Channel', 'tx_kiosky_domain_model_channel'),
    'playlist' => T::relation('Playlist', 'tx_kiosky_domain_model_playlist'),
    'slide' => T::relation('Slide', 'tx_kiosky_domain_model_slide'),
    'automatic_return' => T::toggle('Automatic return'),
    'return_channel' => T::relation('Return channel', 'tx_kiosky_domain_model_channel'),
    'confirmation_required' => T::toggle('Confirmation required'),
    'emergency' => T::toggle('Emergency preset'),
]);
