<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_externaldatasource', 'name', [
    'name' => T::input('Name', true),
    'provider' => T::input('Provider', true),
    'base_url' => T::input('Base URL'),
    'configuration_json' => T::text('Configuration'),
    'sync_enabled' => T::toggle('Synchronization enabled'),
    'last_error' => T::text('Last error'),
]);
