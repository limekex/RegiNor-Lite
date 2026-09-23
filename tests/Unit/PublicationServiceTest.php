<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Domain\Publication\Period;
use RegiNor\Lite\Domain\Publication\PublicationService;

final class PublicationServiceTest extends TestCase
{
    private function service(string $now = '2026-10-20T12:00:00Z'): PublicationService
    {
        return new PublicationService(new class ($now) implements Clock {
            public function __construct(private string $instant) {}
            public function now(): DateTimeImmutable { return new DateTimeImmutable($this->instant); }
        });
    }

    private function period(string $id = 'p1', string $first = '2026-10-12T18:00:00+02:00', string $last = '2026-11-23T19:00:00+01:00', bool $upcoming = true, bool $cancelled = false, string $status = 'publish', bool $groups = true): Period
    {
        return new Period($id, $status, new DateTimeImmutable('2026-10-01T00:00:00+02:00'),
            new DateTimeImmutable('2026-12-01T00:00:00+01:00'), new DateTimeImmutable($first), new DateTimeImmutable($last), $groups, $upcoming, $cancelled);
    }

    public static function visibilityBounds(): iterable
    {
        yield 'before' => ['2026-09-30T21:59:59Z', false];
        yield 'exact opening' => ['2026-09-30T22:00:00Z', true];
        yield 'just before closing' => ['2026-11-30T22:59:59Z', true];
        yield 'exact closing' => ['2026-11-30T23:00:00Z', false];
    }

    #[DataProvider('visibilityBounds')]
    public function testVisibilityUsesHalfOpenInstantsAcrossDst(string $instant, bool $expected): void
    {
        self::assertSame($expected, $this->service($instant)->isPublic($this->period()));
    }

    public static function nonPublicStatuses(): iterable
    {
        foreach (['draft', 'private', 'trash', 'pending', 'future', 'unknown'] as $status) {
            yield $status => [$status];
        }
    }

    #[DataProvider('nonPublicStatuses')]
    public function testNonPublicStatusesFailClosed(string $status): void
    {
        self::assertFalse($this->service()->isPublic($this->period(status: $status)));
        self::assertFalse($this->service()->isGroupPublic($this->period(), $status));
    }

    public function testMissingAndInvalidWindowsOrGroupsFailClosed(): void
    {
        $now = new DateTimeImmutable('2026-10-20T12:00:00Z');
        foreach ([[null, $now], [$now, null], [$now, $now], [$now->modify('+1 day'), $now]] as [$from, $until]) {
            self::assertFalse($this->service()->isPublic(new Period('p', 'publish', $from, $until, null, null, true)));
        }
        self::assertFalse($this->service()->isPublic($this->period(groups: false)));
        self::assertFalse($this->service()->isGroupPublic($this->period(status: 'draft'), 'publish'));
        self::assertTrue($this->service()->isGroupPublic($this->period(), 'publish'));
    }

    public function testBreakWeekRemainsCurrentAndMostRecentlyStartedWins(): void
    {
        $early = $this->period('early');
        $laterB = $this->period('b', '2026-10-19T18:00:00+02:00');
        $laterA = $this->period('a', '2026-10-19T18:00:00+02:00');
        $future = $this->period('next', '2026-11-02T18:00:00+01:00');
        $choices = $this->service()->choices([$future, $early, $laterB, $laterA]);
        self::assertSame(['a', 'b', 'early'], array_map(static fn (Period $p): string => $p->id, $choices['current']));
        self::assertSame($laterA, $choices['default']);
        self::assertSame([$future], $choices['upcoming']);
    }

    public function testNearestUpcomingAndStableTieBreak(): void
    {
        $b = $this->period('b', '2026-11-02T18:00:00+01:00');
        $a = $this->period('a', '2026-11-02T18:00:00+01:00');
        $late = $this->period('later', '2026-11-09T18:00:00+01:00');
        $choices = $this->service()->choices([$late, $b, $a]);
        self::assertSame([$a, $b, $late], $choices['upcoming']);
        self::assertSame($a, $choices['default']);
    }

    public function testUpcomingFlagDoesNotHideDirectPage(): void
    {
        $period = $this->period(first: '2026-11-02T18:00:00+01:00', upcoming: false);
        self::assertTrue($this->service()->isPublic($period));
        self::assertNull($this->service()->choices([$period])['default']);
    }

    public function testEndedCancelledAndDraftAreNotRecommended(): void
    {
        $ended = $this->period(last: '2026-10-19T19:00:00+02:00');
        $cancelled = $this->period('cancelled', cancelled: true);
        $draft = $this->period('draft', status: 'draft');
        self::assertTrue($this->service()->isPublic($ended));
        self::assertTrue($this->service()->isPublic($cancelled));
        self::assertSame(['current' => [], 'upcoming' => [], 'default' => null], $this->service()->choices([$ended, $cancelled, $draft]));
    }

    public function testExactLessonBoundaries(): void
    {
        $period = $this->period();
        self::assertSame([$period], $this->service('2026-10-12T18:00:00+02:00')->choices([$period])['current']);
        self::assertSame([], $this->service('2026-11-23T19:00:00+01:00')->choices([$period])['current']);
    }

    public function testInvalidSessionBoundsNeverBecomeDefault(): void
    {
        $period = $this->period(first: '2026-11-23T19:00:00+01:00', last: '2026-10-12T18:00:00+02:00');
        self::assertNull($this->service()->choices([$period])['default']);
    }

    public function testDuplicatePeriodIdsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->choices([$this->period(), $this->period()]);
    }
}
