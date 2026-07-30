<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_schedule', 'name', [
    'name' => T::input('Name', true),
    'target_type' => T::input('Target type'),
    'target_uid' => T::integer('Target record'),
    'content_type' => T::input('Content type'),
    'content_uid' => T::integer('Content record'),
    'recurrence' => T::input('Recurrence'),
    'weekdays' => T::input('Weekdays'),
    'priority' => T::integer('Priority'),
]);
