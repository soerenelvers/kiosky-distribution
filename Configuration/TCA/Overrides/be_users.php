<?php

defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$columns = [
    'tx_kiosky_role' => [
        'exclude' => true,
        'label' => 'Kiosky-Rolle',
        'description' => 'Steuert die Berechtigungen des CMS-Benutzers innerhalb von Kiosky.',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'Automatisch (TYPO3-Administrator / Redakteur)', 'value' => ''],
                ['label' => 'Kiosky Administrator', 'value' => 'administrator'],
                ['label' => 'Kiosky Manager', 'value' => 'manager'],
                ['label' => 'Kiosky Redakteur', 'value' => 'editor'],
                ['label' => 'Kiosky Veranstaltungsredaktion', 'value' => 'event_editor'],
                ['label' => 'Kiosky Display-Steuerung', 'value' => 'display_operator'],
                ['label' => 'Kiosky Nur-Lesen', 'value' => 'viewer'],
            ],
            'default' => '',
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('be_users', $columns);
ExtensionManagementUtility::addToAllTCAtypes('be_users', 'tx_kiosky_role', '', 'after:admin');
