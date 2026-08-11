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
        return PublicEventCalendar::render($this->store->load(), [
            'title' => (string)($record['tx_kiosky_calendar_title'] ?? ''),
            'upcomingTitle' => (string)($record['tx_kiosky_upcoming_title'] ?? ''),
            'upcomingCount' => ($record['tx_kiosky_upcoming_count'] ?? '') === '' ? null : (int)$record['tx_kiosky_upcoming_count'],
            'showSearch' => array_key_exists('tx_kiosky_show_search', $record) ? (bool)$record['tx_kiosky_show_search'] : null,
            'showUpcoming' => array_key_exists('tx_kiosky_show_upcoming', $record) ? (bool)$record['tx_kiosky_show_upcoming'] : null,
        ]);
    }
}
