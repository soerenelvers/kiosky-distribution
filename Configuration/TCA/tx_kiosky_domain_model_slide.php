<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_slide', 'name', [
    'name' => T::input('Name', true),
    'public_uuid' => T::input('Public UUID', true),
    'slide_type' => T::input('Slide type', true),
    'template' => T::relation('Template', 'tx_kiosky_domain_model_template'),
    'content_json' => T::text('Structured content'),
    'media' => T::file('Media', ['jpg', 'jpeg', 'png', 'webp', 'svg', 'mp4', 'webm', 'pdf']),
    'duration' => T::integer('Duration in seconds', 10),
    'weekdays' => T::input('Weekdays'),
    'priority' => T::integer('Priority'),
    'transition_name' => T::input('Transition'),
]);
