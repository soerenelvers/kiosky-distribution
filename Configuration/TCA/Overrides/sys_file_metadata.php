<?php

declare(strict_types=1);
defined('TYPO3') || die();
$GLOBALS['TCA']['sys_file_metadata']['columns']['tx_kiosky_data'] = [
    'label' => 'Kiosky Medienmetadaten',
    'config' => ['type' => 'passthrough'],
];
