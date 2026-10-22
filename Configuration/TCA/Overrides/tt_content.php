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
        'description' => '0 übernimmt die zentrale Vorgabe.',
        'config' => ['type' => 'number', 'range' => ['lower' => 0, 'upper' => 50], 'default' => 0],
    ],
    'tx_kiosky_show_search' => [
        'exclude' => true,
        'label' => 'Kalendersuche anzeigen',
        'config' => ['type'=>'select','renderType'=>'selectSingle','items'=>[['label'=>'Zentrale Vorgabe','value'=>-1],['label'=>'Anzeigen','value'=>1],['label'=>'Ausblenden','value'=>0]],'default'=>-1],
    ],
    'tx_kiosky_show_upcoming' => [
        'exclude' => true,
        'label' => 'Nächste Veranstaltungen anzeigen',
        'config' => ['type'=>'select','renderType'=>'selectSingle','items'=>[['label'=>'Zentrale Vorgabe','value'=>-1],['label'=>'Anzeigen','value'=>1],['label'=>'Ausblenden','value'=>0]],'default'=>-1],
    ],
]);

$extraColumns=[
    'tx_kiosky_cal_view'=>['exclude'=>true,'label'=>'Kalenderansicht','config'=>['type'=>'select','renderType'=>'selectSingle','default'=>'','items'=>[['label'=>'Zentrale Vorgabe','value'=>''],['label'=>'Agenda','value'=>'agenda'],['label'=>'Monatskalender','value'=>'month'],['label'=>'Kategorien-Explorer','value'=>'categories'],['label'=>'Wochenkalender','value'=>'week'],['label'=>'Tagesfokus mit Wochenleiste','value'=>'day']]]],
    'tx_kiosky_cal_color_mode'=>['exclude'=>true,'label'=>'Farbwahl','config'=>['type'=>'select','renderType'=>'selectSingle','default'=>'','items'=>[['label'=>'Zentrale Vorgabe','value'=>''],['label'=>'Automatisch aus dem Website-Theme','value'=>'theme'],['label'=>'Individuelle Farben','value'=>'custom']]]],
];
foreach(['accent_color'=>'Akzentfarbe','accent_text_color'=>'Text auf Akzentflächen','control_background'=>'Steuerelemente: Hintergrund','control_text_color'=>'Steuerelemente: Text','control_border_color'=>'Steuerelemente: Rahmen'] as $field=>$label)$extraColumns['tx_kiosky_cal_'.$field]=['exclude'=>true,'label'=>$label,'description'=>'Hex-Farbe wie #1968d2. Leer übernimmt die zentrale Farbe. Bei individueller Farbwahl wirksam.','config'=>['type'=>'input','max'=>7,'eval'=>'trim','default'=>'']];
$columnFields=[];
foreach(['show_time'=>'Uhrzeit und Einlass','show_location'=>'Ort und Raum','show_category'=>'Kategorie','show_organizer'=>'Veranstalter','show_description'=>'Beschreibung und öffentliche Hinweise','show_status'=>'Absage / ausverkauft','show_tickets'=>'Ticket-Link','show_remaining_tickets'=>'Restkarten / Ticketinformation','show_details'=>'Detail-Schaltfläche'] as $field=>$label){$name='tx_kiosky_cal_'.$field;$columnFields[]=$name;$extraColumns[$name]=['exclude'=>true,'label'=>$label,'config'=>['type'=>'select','renderType'=>'selectSingle','default'=>-1,'items'=>[['label'=>'Zentrale Vorgabe','value'=>-1],['label'=>'Anzeigen','value'=>1],['label'=>'Ausblenden','value'=>0]]]];}
ExtensionManagementUtility::addTCAcolumns('tt_content',$extraColumns);

ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    'label' => 'Kiosky Veranstaltungskalender',
    'value' => 'kiosky_event_calendar',
    'icon' => 'content-kiosky-calendar',
    'group' => 'special',
]);

$GLOBALS['TCA']['tt_content']['types']['kiosky_event_calendar'] = [
    'showitem' => '--palette--;;general,header,--palette--;;headers,tx_kiosky_calendar_title,tx_kiosky_show_search,tx_kiosky_show_upcoming,tx_kiosky_upcoming_title,tx_kiosky_upcoming_count,--div--;Darstellung,tx_kiosky_cal_view,tx_kiosky_cal_color_mode,tx_kiosky_cal_accent_color,tx_kiosky_cal_accent_text_color,tx_kiosky_cal_control_background,tx_kiosky_cal_control_text_color,tx_kiosky_cal_control_border_color,--div--;Spalten und Optionen,'.implode(',',$columnFields).',--div--;Zugriff,--palette--;;hidden,--palette--;;access',
];
