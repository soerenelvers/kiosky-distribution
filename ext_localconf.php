<?php

defined('TYPO3') || die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
    '@import "EXT:kiosky/Configuration/TypoScript/setup.typoscript"'
);

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('scheduler')) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Kiosky\Kiosky\Task\LicenseSyncTask::class]=['extension'=>'kiosky','title'=>'Kiosky: Lizenzfreigaben abgleichen','description'=>'Alle 15 Minuten fällige Modulfreigaben prüfen. Aufgabe jede Minute ausführen.'];
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Kiosky\Kiosky\Task\MenuPdfSyncTask::class] = [
        'extension'=>'kiosky',
        'title'=>'Kiosky: Speisekarten-PDF-Weblinks',
        'description'=>'Prüft fällige PDF-Abonnements. Aufgabe jede Minute ausführen.',
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Kiosky\Kiosky\Task\CrewBrainImportTask::class] = [
        'extension' => 'kiosky',
        'title' => 'Kiosky: CrewBrain-Import',
        'description' => 'Prüft regelmäßig, ob der tägliche CrewBrain-Import fällig ist.',
    ];
}
