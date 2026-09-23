<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Calendar;

/** RFC 5545 serialization. No recurrence guesses, invitations or attendee data. */
final class ICalendar
{
    public static function text(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return str_replace(["\\", "\r\n", "\r", "\n", ';', ','], ["\\\\", '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }

    public static function fold(string $line): string
    {
        $lines = []; $part = ''; $bytes = 0;
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($bytes + strlen($character) > 75) { $lines[] = $part; $part = ' '; $bytes = 1; }
            $part .= $character; $bytes += strlen($character);
        }
        $lines[] = $part;
        return implode("\r\n", $lines);
    }

    private static function utc(string $value): string
    {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    public static function render(string $name, string $identity, array $events): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//RegiNor Lite//Course Calendar//NO', 'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:' . self::text($name)];
        foreach ($events as $id => $event) {
            $lines = [...$lines, 'BEGIN:VEVENT', 'UID:' . $identity . '-' . hash('sha256', (string) $id) . '@reginor-lite',
                'DTSTAMP:' . self::utc($event['modified']), 'LAST-MODIFIED:' . self::utc($event['modified']), 'SEQUENCE:' . (int) $event['sequence'],
                'DTSTART:' . self::utc($event['start']), 'DTEND:' . self::utc($event['end']), 'SUMMARY:' . self::text($event['title']),
                'DESCRIPTION:' . self::text($event['description']), 'LOCATION:' . self::text($event['location']),
                // URI is not a TEXT value. Strip control characters rather than escaping its query delimiters.
                'URL:' . preg_replace('/[\x00-\x20\x7F]/', '', $event['url']),
                'STATUS:' . ($event['cancelled'] ? 'CANCELLED' : 'CONFIRMED'), 'END:VEVENT'];
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }
}
