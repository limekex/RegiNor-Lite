<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RegiNor\Lite\Domain\Analytics\JourneyEvent;

final class JourneyEventTest extends TestCase
{
    private function event(): array
    {
        return ['event_id' => '166e7f62-dc56-4fa7-9516-568836e54318', 'journey_id' => 'f3cdcc12-8d9c-4b81-a1c2-f65386488f63',
            'stage' => 'letsreg_click', 'course_id' => 24, 'page_id' => 12, 'statistics' => true, 'marketing' => true,
            'campaign' => ['utm_source' => 'google', 'utm_campaign' => 'Høst 2026', 'utm_term' => 'test@example.com', 'unknown' => 'secret'],
            'click_ids' => ['gclid' => 'SYNTHETIC-only_123', 'fbclid' => 'bad@identifier', 'unknown' => 'secret'], 'email' => 'secret@example.com', 'url' => 'https://example.com/?private=yes'];
    }
    public function testOnlyExplicitFieldsSurviveAndMarketingRequiresBothConsents(): void
    {
        $input = $this->event(); $event = JourneyEvent::validate($input, true);
        self::assertSame('Høst 2026', $event['campaign']['utm_campaign']);
        self::assertSame('', $event['campaign']['utm_term']);
        self::assertSame(['gclid' => 'SYNTHETIC-only_123'], $event['click_ids']);
        self::assertArrayNotHasKey('email', $event); self::assertArrayNotHasKey('url', $event);
        self::assertArrayNotHasKey('unknown', $event['campaign']);
        self::assertSame([], JourneyEvent::validate($input, false)['click_ids']);
        $input['marketing'] = false; self::assertSame([], JourneyEvent::validate($input, true)['click_ids']);
    }
    public static function invalid(): array
    {
        return [['stage', 'purchase'], ['stage', 'registration'], ['statistics', false], ['statistics', 'true'], ['event_id', ''],
            ['journey_id', 'email@example.com'], ['course_id', -1], ['page_id', '12'], ['page_id', 2147483648]];
    }
    #[DataProvider('invalid')]
    public function testUntrustedClaimsAreRejected(string $key, mixed $value): void
    {
        $input = $this->event(); $input[$key] = $value;
        $this->expectException(InvalidArgumentException::class); JourneyEvent::validate($input, true);
    }
    public function testInvalidOrOversizedLabelsAreOmitted(): void
    {
        foreach (['https://example.com', '<script>', "new\nline", str_repeat('ø', 51), ['x']] as $value) {
            self::assertSame('', JourneyEvent::label($value));
        }
        self::assertSame('Høst-kurs_2026', JourneyEvent::label(' Høst-kurs_2026 '));
    }
}
