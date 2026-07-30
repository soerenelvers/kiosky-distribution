<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_event', 'title', [
    'title' => T::input('Title', true),
    'subtitle' => T::input('Subtitle'),
    'description' => T::text('Description'),
    'event_date' => T::dateTime('Date'),
    'event_start' => T::dateTime('Start'),
    'admission_start' => T::dateTime('Admission'),
    'break_start' => T::dateTime('Break'),
    'event_end' => T::dateTime('End'),
    'setup_start' => T::dateTime('Setup'),
    'teardown_end' => T::dateTime('Teardown'),
    'location' => T::relation('Location', 'tx_kiosky_domain_model_location'),
    'hall' => T::input('Hall'),
    'organizer' => T::input('Organizer'),
    'image' => T::file('Image', ['jpg', 'jpeg', 'png', 'webp']),
    'notes' => T::text('Notes'),
    'channel' => T::relation('Channel', 'tx_kiosky_domain_model_channel'),
    'playlist' => T::relation('Playlist', 'tx_kiosky_domain_model_playlist'),
    'status' => T::input('Status'),
    'external_id' => T::input('External ID'),
    'external_data_source' => T::relation('External source', 'tx_kiosky_domain_model_externaldatasource'),
]);
