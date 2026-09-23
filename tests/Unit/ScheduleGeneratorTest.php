<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\LocalDateTime;
use RegiNor\Lite\Domain\Scheduling\BreakPeriod;
use RegiNor\Lite\Domain\Scheduling\ScheduleGenerator;
use RegiNor\Lite\Domain\Scheduling\ScheduleRequest;
use RegiNor\Lite\Domain\Scheduling\ScheduledTime;

final class ScheduleGeneratorTest extends TestCase
{
    private function dates(ScheduleRequest $request): array
    {
        return array_map(static fn (ScheduledTime $time): string => $time->startsAt->format('Y-m-d'), (new ScheduleGenerator())->preview($request));
    }

    public function testSixMondaysAndOverlappingBreaks(): void
    {
        self::assertSame(['2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02', '2026-11-09', '2026-11-16'],
            $this->dates(new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 6)));
        self::assertSame(['2026-10-12', '2026-10-19', '2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23'],
            $this->dates(new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 6,
                [new BreakPeriod('2026-10-26', '2026-11-01')], [new BreakPeriod('2026-10-26', '2026-10-26')])));
    }

    public function testIrrelevantBreakDoesNotReduceCountAndGroupBreakExtendsSchedule(): void
    {
        self::assertSame(['2026-10-12', '2026-10-26'], $this->dates(new ScheduleRequest(
            '2026-10-12', 1, '18:00', '19:00', 2,
            [new BreakPeriod('2026-10-13', '2026-10-13')], [new BreakPeriod('2026-10-19', '2026-10-19')]
        )));
    }

    public function testMultiweekBreakIncludesBothEnds(): void
    {
        self::assertSame(['2026-10-12', '2026-11-09'], $this->dates(new ScheduleRequest(
            '2026-10-12', 1, '18:00', '19:00', 2, [new BreakPeriod('2026-10-19', '2026-11-02')]
        )));
    }

    public static function weekdays(): iterable
    {
        for ($day = 1; $day <= 7; ++$day) {
            yield "ISO $day" => [$day, '2026-10-' . (11 + $day)];
        }
    }

    #[DataProvider('weekdays')]
    public function testEveryIsoWeekday(int $day, string $expected): void
    {
        self::assertSame([$expected], $this->dates(new ScheduleRequest('2026-10-12', $day, '18:00', '19:00', 1)));
    }

    public function testMovesForwardToNextWeekday(): void
    {
        self::assertSame(['2026-10-19'], $this->dates(new ScheduleRequest('2026-10-13', 1, '18:00', '19:00', 1)));
    }

    public static function seasonalChanges(): iterable
    {
        yield 'winter' => ['2026-10-19', ['+02:00', '+01:00'], ['16:00', '17:00']];
        yield 'summer' => ['2026-03-23', ['+01:00', '+02:00'], ['17:00', '16:00']];
    }

    #[DataProvider('seasonalChanges')]
    public function testLocalTimesSurviveDst(string $start, array $offsets, array $utcTimes): void
    {
        $times = (new ScheduleGenerator())->preview(new ScheduleRequest($start, 1, '18:00', '19:00', 2));
        foreach ($times as $i => $time) {
            self::assertSame('18:00', $time->startsAt->format('H:i'));
            self::assertSame($offsets[$i], $time->startsAt->format('P'));
            self::assertSame($utcTimes[$i], $time->startsAtUtc()->format('H:i'));
            self::assertSame(3600, $time->endsAtUtc()->getTimestamp() - $time->startsAtUtc()->getTimestamp());
        }
    }

    public static function invalidLocalTimes(): iterable
    {
        yield 'nonexistent DST time' => ['2026-03-29', '02:30', 'Europe/Oslo'];
        yield 'ambiguous DST time' => ['2026-10-25', '02:30', 'Europe/Oslo'];
        yield 'invalid date' => ['2026-02-30', '18:00', 'Europe/Oslo'];
        yield 'invalid leap day' => ['2026-02-29', '18:00', 'Europe/Oslo'];
        yield 'relative date' => ['tomorrow', '18:00', 'Europe/Oslo'];
        yield 'invalid time' => ['2026-10-12', '24:00', 'Europe/Oslo'];
        yield 'invalid minute' => ['2026-10-12', '18:60', 'Europe/Oslo'];
        yield 'invalid zone' => ['2026-10-12', '18:00', 'Oslo'];
    }

    #[DataProvider('invalidLocalTimes')]
    public function testRejectsInvalidOrAmbiguousInput(string $date, string $time, string $zone): void
    {
        $this->expectException(InvalidArgumentException::class);
        LocalDateTime::at($date, $time, $zone);
    }

    public function testLeapDayAndUtcAreSupported(): void
    {
        self::assertSame('2028-02-29T18:00:00+00:00', LocalDateTime::at('2028-02-29', '18:00', 'UTC')->format('c'));
    }

    public function testAbsoluteEndIsInclusive(): void
    {
        self::assertCount(2, $this->dates(new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 2, latestDate: '2026-10-19')));
        $this->expectExceptionMessage('3 undervisningskvelder, men bare 2 får plass før siste tillatte kursdato 2026-10-19');
        $this->dates(new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 3, latestDate: '2026-10-19'));
    }

    public function testSearchHorizonStopsFullyBlockedSchedule(): void
    {
        $this->expectExceptionMessage('søkehorisonten');
        (new ScheduleGenerator(4))->preview(new ScheduleRequest(
            '2026-10-12', 1, '18:00', '19:00', 1, [new BreakPeriod('2026-01-01', '2027-12-31')]
        ));
    }

    public function testExplicitFirstDateOverridesSuggestedStart(): void
    {
        self::assertSame(['2026-10-19'], $this->dates(new ScheduleRequest('2026-11-01', 1, '18:00', '19:00', 1, firstDate: '2026-10-19')));
    }

    public function testExplicitFirstDateCannotSilentlySkipBreak(): void
    {
        $this->expectExceptionMessage('Første kursdato 2026-10-19 er satt som kursfri');
        $this->dates(new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 1,
            [new BreakPeriod('2026-10-19', '2026-10-19')], firstDate: '2026-10-19'));
    }

    public static function invalidRequests(): iterable
    {
        yield 'zero weekday' => [0, '18:00', '19:00', 1, null];
        yield 'eighth weekday' => [8, '18:00', '19:00', 1, null];
        yield 'no sessions' => [1, '18:00', '19:00', 0, null];
        yield 'negative count' => [1, '18:00', '19:00', -1, null];
        yield 'empty interval' => [1, '18:00', '18:00', 1, null];
        yield 'reversed interval' => [1, '19:00', '18:00', 1, null];
        yield 'wrong explicit weekday' => [1, '18:00', '19:00', 1, '2026-10-13'];
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidScheduleIsRejected(int $day, string $start, string $end, int $count, ?string $first): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduleRequest('2026-10-12', $day, $start, $end, $count, firstDate: $first);
    }
}
