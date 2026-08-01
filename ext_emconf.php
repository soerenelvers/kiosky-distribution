<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Kiosky',
    'description' => 'TYPO3-native digital signage, display control and event information.',
    'category' => 'module',
    'author' => 'Sören Elvers',
    'author_email' => '',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'version' => '3.1.3',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.4.99',
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'scheduler' => '',
        ],
    ],
];
