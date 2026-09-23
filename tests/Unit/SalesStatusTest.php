<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RegiNor\Lite\Domain\Publication\SalesStatus;

final class SalesStatusTest extends TestCase
{
    public static function cases(): array
    {
        return [
            [false, false, 'available', '2026-10-10T12:00:00Z', 'hidden'],
            [true, true, 'available', '2026-10-10T12:00:00Z', 'cancelled'],
            [true, false, 'cancelled', '2026-10-10T12:00:00Z', 'cancelled'],
            [true, false, 'available', '2026-10-01T11:59:59Z', 'later'],
            [true, false, 'available', '2026-10-01T12:00:00Z', 'available'],
            [true, false, 'available', '2026-11-01T11:59:59Z', 'available'],
            [true, false, 'available', '2026-11-01T12:00:00Z', 'closed'],
            [true, false, 'full', '2026-10-10T12:00:00Z', 'full'],
            [true, false, 'waiting', '2026-10-10T12:00:00Z', 'waiting'],
            [true, false, 'unknown', '2026-10-10T12:00:00Z', 'unknown'],
        ];
    }

    #[DataProvider('cases')]
    public function testIndependentWindows(bool $visible, bool $cancelled, string $editorial, string $now, string $expected): void
    {
        self::assertSame($expected, (new SalesStatus())->effective($visible, $cancelled, $editorial,
            new DateTimeImmutable('2026-10-01T12:00:00Z'), new DateTimeImmutable('2026-11-01T12:00:00Z'), new DateTimeImmutable($now)));
    }

    public function testLocalModes(): void
    {
        $status = new SalesStatus(); $now = new DateTimeImmutable('2026-09-21T12:00:00Z');
        self::assertSame('dropin', $status->effective(true, false, 'dropin', null, null, $now));
        self::assertSame('hidden', $status->effective(false, false, 'dropin', null, null, $now));
        self::assertSame('cancelled', $status->effective(true, true, 'dropin', null, null, $now));
        self::assertSame('external', $status->effective(true, false, 'external', $now->modify('-1 day'), $now->modify('+1 day'), $now));
        self::assertSame('closed', $status->effective(true, false, 'external', $now->modify('-2 days'), $now->modify('-1 day'), $now));
    }

    public function testMissingWindowsFailClosed(): void
    {
        self::assertSame('closed', (new SalesStatus())->effective(true, false, 'available', null, null, new DateTimeImmutable()));
    }
}
