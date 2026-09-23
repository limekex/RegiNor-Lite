<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\Calendar\ICalendar;

final class ICalendarTest extends TestCase
{
    public function testUntrustedTextCannotInjectPropertiesAndFoldingPreservesUnicode(): void
    {
        $name = str_repeat('Øvet 🕺 ', 24) . ", sal; A\\B\r\nATTENDEE:malicious";
        $escaped = ICalendar::text($name);
        $folded = ICalendar::fold('SUMMARY:' . $escaped);
        foreach (explode("\r\n", $folded) as $line) {
            self::assertLessThanOrEqual(75, strlen($line));
            self::assertSame(1, preg_match('//u', $line));
        }
        self::assertSame('SUMMARY:' . $escaped, str_replace("\r\n ", '', $folded));
        self::assertStringNotContainsString("\r\nATTENDEE:", $folded);
        self::assertStringContainsString('\\, sal\\; A\\\\B\\nATTENDEE:', $escaped);
    }

    public function testUtcTimesRevisionsAndNoImplicitInvitations(): void
    {
        $event = ['title'=>'Salsa', 'start'=>'2030-10-21T18:30:00+02:00', 'end'=>'2030-10-21T20:00:00+02:00',
            'cancelled'=>true, 'location'=>'Sal A', 'description'=>'Flyttet', 'url'=>"https://example.org/kurs/?a=1&b=2\r\nATTENDEE:x", 'modified'=>'2026-09-20T10:00:00Z', 'sequence'=>3];
        $ics = ICalendar::render('Kurs', 'stable-calendar', ['stable-session'=>$event]);
        self::assertStringContainsString('DTSTART:20301021T163000Z', $ics);
        self::assertStringContainsString('DTEND:20301021T180000Z', $ics);
        self::assertStringContainsString("STATUS:CANCELLED\r\n", $ics);
        self::assertStringContainsString("SEQUENCE:3\r\n", $ics);
        self::assertStringNotContainsString("\r\nATTENDEE:", $ics);
        self::assertStringNotContainsString('METHOD:', $ics);
        self::assertStringNotContainsString('VALARM', $ics);
        self::assertStringContainsString('a=1&b=2', $ics);
        self::assertSame(0, preg_match('/(?<!\r)\n/', $ics));
        $event['start']='2030-10-28T18:30:00+01:00';$event['end']='2030-10-28T20:00:00+01:00';
        self::assertStringContainsString('DTSTART:20301028T173000Z', ICalendar::render('Kurs','stable-calendar',['stable-session'=>$event]));
    }
}
