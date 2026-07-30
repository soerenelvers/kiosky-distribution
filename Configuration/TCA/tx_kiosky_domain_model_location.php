<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_location', 'name', [
    'name' => T::input('Name', true),
    'organisation' => T::input('Organisation'),
    'street' => T::input('Street'),
    'house_number' => T::input('House number'),
    'postal_code' => T::input('Postal code'),
    'city' => T::input('City'),
    'country' => T::input('Country'),
    'building' => T::input('Building'),
    'floor' => T::input('Floor'),
    'room' => T::input('Room'),
    'latitude' => T::input('Latitude'),
    'longitude' => T::input('Longitude'),
    'timezone' => T::input('Timezone'),
    'description' => T::text('Description'),
]);
