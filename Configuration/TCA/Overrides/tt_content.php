<?php

defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_kiosky_calendar_title' => [
        'exclude' => true,
        'label' => 'Kalenderüberschrift',
        'description' => 'Leer lassen, um die zentrale Kiosky-Einstellung zu verwenden.',
        'config' => ['type' => 'input', 'max' => 120],
    ],
    'tx_kiosky_upcoming_title' => [
        'exclude' => true,
        'label' => 'Überschrift der nächsten Veranstaltungen',
        'description' => 'Leer lassen, um die zentrale Kiosky-Einstellung zu verwenden.',
        'config' => ['type' => 'input', 'max' => 120],
    ],
    'tx_kiosky_upcoming_count' => [
        'exclude' => true,
        'label' => 'Anzahl der nächsten Veranstaltungen',
        'config' => ['type' => 'number', 'range' => ['lower' => 1, 'upper' => 50], 'default' => 10],
    ],
    'tx_kiosky_show_search' => [
        'exclude' => true,
        'label' => 'Kalendersuche anzeigen',
        'config' => ['type' => 'check', 'default' => 1],
    ],
    'tx_kiosky_show_upcoming' => [
        'exclude' => true,
        'label' => 'Nächste Veranstaltungen anzeigen',
        'config' => ['type' => 'check', 'default' => 1],
    ],
]);

ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    'label' => 'Kiosky Veranstaltungskalender',
    'value' => 'kiosky_event_calendar',
    'icon' => 'content-kiosky-calendar',
    'group' => 'special',
]);

$GLOBALS['TCA']['tt_content']['types']['kiosky_event_calendar'] = [
    'showitem' => '--palette--;;general,header,--palette--;;headers,tx_kiosky_calendar_title,tx_kiosky_show_search,tx_kiosky_show_upcoming,tx_kiosky_upcoming_title,tx_kiosky_upcoming_count,--div--;Zugriff,--palette--;;hidden,--palette--;;access',
];
