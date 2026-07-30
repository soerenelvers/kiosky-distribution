<?php

use Kiosky\Kiosky\Utility\TcaFactory as T;
return T::create('tx_kiosky_domain_model_emergencymessage', 'title', [
    'title' => T::input('Title', true),
    'message' => T::text('Message'),
    'severity' => T::input('Severity'),
    'graphic' => T::file('Graphic', ['jpg', 'jpeg', 'png', 'webp', 'svg']),
    'target_displays' => T::text('Target displays'),
    'target_groups' => T::text('Target groups'),
    'active' => T::toggle('Active'),
]);
