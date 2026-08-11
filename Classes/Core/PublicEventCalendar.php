<?php

declare(strict_types=1);

namespace Kiosky\Core;

final class PublicEventCalendar
{
    public const DEFAULTS = [
        'title' => 'Veranstaltungskalender',
        'upcomingTitle' => 'Nächste Veranstaltungen',
        'upcomingCount' => 10,
        'showSearch' => true,
        'showUpcoming' => true,
    ];

    /** @param array<string,mixed> $input @return array{title:string,upcomingTitle:string,upcomingCount:int,showSearch:bool,showUpcoming:bool} */
    public static function normalizeSettings(array $input): array
    {
        $settings = array_replace(self::DEFAULTS, $input);
        return [
            'title' => self::text($settings['title'] ?? '', 120) ?: self::DEFAULTS['title'],
            'upcomingTitle' => self::text($settings['upcomingTitle'] ?? '', 120) ?: self::DEFAULTS['upcomingTitle'],
            'upcomingCount' => max(1, min(50, (int)($settings['upcomingCount'] ?? self::DEFAULTS['upcomingCount']))),
            'showSearch' => filter_var($settings['showSearch'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'showUpcoming' => filter_var($settings['showUpcoming'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        ];
    }

    /** @param array<string,mixed> $state @return list<array<string,mixed>> */
    public static function events(array $state): array
    {
        $events = [];
        foreach (is_array($state['events'] ?? null) ? $state['events'] : [] as $event) {
            if (!is_array($event) || !empty($event['deletedAt'])) continue;
            $status = strtolower((string)($event['status'] ?? ''));
            if (!in_array($status, ['ready', 'published', 'sold_out', 'ended', 'cancelled'], true)) continue;
            $date = self::date($event);
            if ($date === null || self::text($event['title'] ?? '', 300) === '') continue;
            $start = self::dateTime($event['eventStart'] ?? null, $date);
            $end = self::dateTime($event['eventEnd'] ?? null, $date);
            $events[] = array_filter([
                'id' => self::text($event['id'] ?? '', 100),
                'title' => self::text($event['title'] ?? '', 300),
                'subtitle' => self::text($event['subtitle'] ?? '', 300),
                'description' => self::text($event['description'] ?? '', 5000),
                'publicNotes' => self::text($event['publicNotes'] ?? '', 2000),
                'organizer' => self::text($event['organizer'] ?? '', 300),
                'eventType' => self::text($event['eventType'] ?? $event['type'] ?? '', 200),
                'venue' => self::text($event['venue'] ?? '', 300),
                'room' => self::text($event['room'] ?? '', 300),
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'admission' => self::dateTime($event['admissionStart'] ?? null, $date),
                'status' => $status ?: 'published',
                'remainingTickets' => self::text($event['remainingTickets'] ?? '', 200),
                'ticketUrl' => self::safeUrl($event['ticketUrl'] ?? null),
            ], static fn(mixed $value): bool => $value !== null && $value !== '');
        }
        usort($events, static fn(array $left, array $right): int => strcmp((string)($left['start'] ?? $left['date']), (string)($right['start'] ?? $right['date'])) ?: strcmp((string)$left['title'], (string)$right['title']));
        return $events;
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $overrides */
    public static function render(array $state, array $overrides = []): string
    {
        $settings = self::normalizeSettings(array_replace(
            is_array($state['publicCalendarSettings'] ?? null) ? $state['publicCalendarSettings'] : [],
            array_filter($overrides, static fn(mixed $value): bool => $value !== null && $value !== '')
        ));
        $payload = json_encode(['events' => self::events($state), 'settings' => $settings], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $id = 'kiosky-calendar-' . bin2hex(random_bytes(6));
        $search = $settings['showSearch'] ? '<label class="kiosky-calendar__search"><span>Kalender durchsuchen</span><input type="search" data-kiosky-search autocomplete="off" placeholder="Veranstaltung, Ort oder Stichwort"></label>' : '';
        $aside = $settings['showUpcoming'] ? '<aside class="kiosky-calendar__upcoming" aria-labelledby="' . $id . '-upcoming"><h3 id="' . $id . '-upcoming">' . self::escape($settings['upcomingTitle']) . '</h3><div data-kiosky-upcoming></div></aside>' : '';
        return '<section class="kiosky-event-calendar" id="' . $id . '" data-kiosky-event-calendar>'
            . '<header class="kiosky-calendar__header"><h2>' . self::escape($settings['title']) . '</h2>' . $search . '</header>'
            . '<div class="kiosky-calendar__layout"><div class="kiosky-calendar__main">'
            . '<nav class="kiosky-calendar__navigation" aria-label="Kalendernavigation">'
            . '<button type="button" data-kiosky-year="-1" aria-label="Ein Jahr zurück">− 1 Jahr</button><button type="button" data-kiosky-month="-1" aria-label="Einen Monat zurück">‹</button>'
            . '<strong data-kiosky-caption aria-live="polite"></strong>'
            . '<button type="button" data-kiosky-month="1" aria-label="Einen Monat weiter">›</button><button type="button" data-kiosky-year="1" aria-label="Ein Jahr weiter">+ 1 Jahr</button>'
            . '<button type="button" data-kiosky-today>Heute</button><label><span>Zu Datum springen</span><input type="date" data-kiosky-jump></label></nav>'
            . '<div class="kiosky-calendar__weekdays" aria-hidden="true"><span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span></div>'
            . '<div class="kiosky-calendar__grid" data-kiosky-grid role="grid"></div><div class="kiosky-calendar__results" data-kiosky-results aria-live="polite"></div>'
            . '</div>' . $aside . '</div><script type="application/json" data-kiosky-data>' . $payload . '</script></section>';
    }

    /** @param array<string,mixed> $event */
    private static function date(array $event): ?string
    {
        $raw = trim((string)($event['date'] ?? ''));
        if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $raw, $match)) return $match[1] . '-' . $match[2] . '-' . $match[3];
        $start = trim((string)($event['eventStart'] ?? ''));
        if ($start !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $start, $match)) return $match[1] . '-' . $match[2] . '-' . $match[3];
        return null;
    }

    private static function dateTime(mixed $value, string $date): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') return null;
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $raw, $match)) return $date . 'T' . $match[1] . ':' . $match[2] . ':' . ($match[3] ?? '00');
        return strtotime($raw) !== false ? $raw : null;
    }

    private static function text(mixed $value, int $length): string
    {
        $text = trim(strip_tags((string)$value));
        return function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length);
    }

    private static function safeUrl(mixed $value): ?string
    {
        $url = trim((string)$value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
        return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
