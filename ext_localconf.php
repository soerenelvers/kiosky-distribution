<?php

defined('TYPO3') || die();

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('scheduler')) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Kiosky\Kiosky\Task\CrewBrainImportTask::class] = [
        'extension' => 'kiosky',
        'title' => 'Kiosky: CrewBrain-Import',
        'description' => 'Prüft regelmäßig, ob der tägliche CrewBrain-Import fällig ist.',
    ];
}
