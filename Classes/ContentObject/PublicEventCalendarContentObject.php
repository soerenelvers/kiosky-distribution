<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\ContentObject;

use Kiosky\Core\PublicEventCalendar;
use Kiosky\Kiosky\Persistence\Typo3StateStore;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class PublicEventCalendarContentObject
{
    private ?ContentObjectRenderer $contentObjectRenderer = null;

    public function __construct(
        private readonly Typo3StateStore $store,
        private readonly PageRenderer $pageRenderer,
        private readonly \Kiosky\Kiosky\Service\Typo3MediaLibrary $media,
    ) {
    }

    public function setContentObjectRenderer(ContentObjectRenderer $contentObjectRenderer): void
    {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    /** @param array<string,mixed> $configuration */
    public function render(string $content = '', array $configuration = []): string
    {
        $record = is_array($this->contentObjectRenderer?->data ?? null) ? $this->contentObjectRenderer->data : [];
        $this->pageRenderer->addCssFile('EXT:kiosky/Resources/Public/EventCalendar/calendar.css');
        $this->pageRenderer->addJsFooterFile('EXT:kiosky/Resources/Public/EventCalendar/calendar.js');
        if(\Kiosky\Core\LicenseClient::managed()){$license=new \Kiosky\Core\LicenseClient(new \Kiosky\Core\Crypto((string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '')));if(!$license->allows($this->store->load(),'events'))return '';}
        $overrides = [
            'title' => (string)($record['tx_kiosky_calendar_title'] ?? ''),
            'upcomingTitle' => (string)($record['tx_kiosky_upcoming_title'] ?? ''),
            'upcomingCount' => (int)($record['tx_kiosky_upcoming_count'] ?? 0)>0 ? (int)$record['tx_kiosky_upcoming_count'] : null,
            'showSearch' => isset($record['tx_kiosky_show_search']) && (int)$record['tx_kiosky_show_search']>=0 ? (bool)$record['tx_kiosky_show_search'] : null,
            'showUpcoming' => isset($record['tx_kiosky_show_upcoming']) && (int)$record['tx_kiosky_show_upcoming']>=0 ? (bool)$record['tx_kiosky_show_upcoming'] : null,
        ];
        foreach(['view'=>'view','color_mode'=>'colorMode','accent_color'=>'accentColor','accent_text_color'=>'accentTextColor','control_background'=>'controlBackground','control_text_color'=>'controlTextColor','control_border_color'=>'controlBorderColor'] as $field=>$key)if(!empty($record['tx_kiosky_cal_'.$field]))$overrides[$key]=(string)$record['tx_kiosky_cal_'.$field];
        foreach(['show_time'=>'showTime','show_location'=>'showLocation','show_category'=>'showCategory','show_organizer'=>'showOrganizer','show_description'=>'showDescription','show_status'=>'showStatus','show_tickets'=>'showTickets','show_remaining_tickets'=>'showRemainingTickets','show_details'=>'showDetails','show_subtitle'=>'showSubtitle','show_admission'=>'showAdmission','show_break'=>'showBreak','show_end_time'=>'showEndTime','show_image'=>'showImage','show_public_notes'=>'showPublicNotes','show_box_office'=>'showBoxOffice','show_setup_time'=>'showSetupTime','show_event_number'=>'showEventNumber','allow_view_switch'=>'allowViewSwitch'] as $field=>$key)if(isset($record['tx_kiosky_cal_'.$field]) && (int)$record['tx_kiosky_cal_'.$field]>=0)$overrides[$key]=(bool)$record['tx_kiosky_cal_'.$field];
        return PublicEventCalendar::render($this->store->load(),$overrides,[$this->media,'calendarImage']);
    }
}
