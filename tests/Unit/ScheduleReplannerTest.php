<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\LocalDateTime;
use RegiNor\Lite\Domain\Scheduling\BreakPeriod;
use RegiNor\Lite\Domain\Scheduling\ScheduleGenerator;
use RegiNor\Lite\Domain\Scheduling\ScheduleReplanner;
use RegiNor\Lite\Domain\Scheduling\ScheduleRequest;
use RegiNor\Lite\Domain\Scheduling\StoredSession;

final class ScheduleReplannerTest extends TestCase
{
    private function existing(): array
    {
        $times = (new ScheduleGenerator())->preview(new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 3));
        return array_map(static fn ($time, $index) => StoredSession::create('old-' . $index, $time, 10, [20]), $times, array_keys($times));
    }

    private function plan(array $existing, ?ScheduleRequest $request = null, string $now = '2026-10-01T00:00:00Z'): array
    {
        $index = 0;
        return (new ScheduleReplanner())->preview($existing, $request ?? new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 3),
            new DateTimeImmutable($now), 11, [21], static function () use (&$index): string { return 'new-' . ++$index; });
    }

    public function testNormalSessionsRetainIdsWhenTimesAndResourcesChange(): void
    {
        $existing = $this->existing();
        $result = $this->plan($existing, new ScheduleRequest('2026-10-12', 1, '19:00', '20:00', 3));
        self::assertSame(array_column($existing, 'id'), array_column($result['sessions'], 'id'));
        self::assertSame(array_column($existing, 'original_date'), array_column($result['sessions'], 'original_date'));
        self::assertSame('19:00', $result['sessions'][0]['start_time']);
        self::assertSame(11, $result['sessions'][0]['room_id']);
        self::assertCount(3, $result['changes']);
        self::assertSame('18:00', $existing[0]['start_time']);
    }

    public function testMovedSessionSurvivesWithExactIdentityAndResources(): void
    {
        $existing = $this->existing();
        $moved = &$existing[1];
        $moved['date'] = '2026-10-20';
        $moved['starts_at'] = '2026-10-20T16:00:00Z';
        $moved['ends_at'] = '2026-10-20T17:00:00Z';
        $moved['status'] = 'moved';
        $moved['reason'] = 'Flyttet etter avtale';
        $result = $this->plan($existing);
        self::assertSame($moved, $result['sessions'][1]);
        self::assertSame([], $result['issues']);
    }

    public function testCancellationIsPreservedAndReplacementNeedsDecision(): void
    {
        $existing = $this->existing();
        $existing[1]['status'] = 'cancelled';
        $existing[1]['reason'] = 'Instruktøren er syk';
        $result = $this->plan($existing);
        self::assertSame($existing[1], $result['sessions'][1]);
        self::assertNotEmpty($result['issues']);
        self::assertStringContainsString('3 undervisningskvelder totalt', $result['issues'][0]);
        self::assertStringContainsString('2 kvelder som ikke er avlyst', $result['issues'][0]);
        self::assertCount(3, $result['sessions']);
    }

    public function testStartedAndPastSessionsCannotBeRewritten(): void
    {
        $existing = $this->existing();
        $result = $this->plan($existing, new ScheduleRequest('2026-10-12', 1, '19:00', '20:00', 3), '2026-10-19T16:00:00Z');
        self::assertSame($existing[0], $result['sessions'][0]);
        self::assertSame($existing[1], $result['sessions'][1]);
        self::assertSame('19:00', $result['sessions'][2]['start_time']);
    }

    public function testBreakProducesReviewableRemovalAndAddition(): void
    {
        $result = $this->plan($this->existing(), new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 3,
            [new BreakPeriod('2026-10-19', '2026-10-19')]));
        self::assertSame(['old-0', 'old-2', 'new-1'], array_column($result['sessions'], 'id'));
        self::assertSame('2026-11-02', $result['sessions'][2]['original_date']);
        self::assertNotEmpty(array_filter($result['changes'], static fn ($c) => $c['id'] === 'old-1' && $c['after'] === null));
        self::assertSame([], $result['issues']);
    }

    public function testBreakHittingMovedDateIsUnresolved(): void
    {
        $existing = $this->existing();
        $existing[1] = array_replace($existing[1], ['date' => '2026-10-20', 'starts_at' => '2026-10-20T16:00:00Z',
            'ends_at' => '2026-10-20T17:00:00Z', 'status' => 'moved', 'reason' => 'Flyttet']);
        $result = $this->plan($existing, new ScheduleRequest('2026-10-12', 1, '18:00', '19:00', 3,
            [new BreakPeriod('2026-10-20', '2026-10-20')]));
        self::assertSame($existing[1], $result['sessions'][1]);
        self::assertStringContainsString('manuelt flyttet', $result['issues'][0]);
    }

    public function testNoNewSessionsInThePast(): void
    {
        $result = $this->plan([], now: '2026-11-01T00:00:00Z');
        self::assertSame([], $result['sessions']);
        self::assertCount(1, $result['issues'], 'Do not add a misleading count mismatch after rejecting past dates.');
        self::assertStringContainsString('2026-10-12, 2026-10-19, 2026-10-26', $result['issues'][0]);
        self::assertStringContainsString('01.11.2026', $result['issues'][0]);
        self::assertStringContainsString('eksisterende kurs', $result['issues'][0]);
    }

    public function testTamperedUtcTimesAreRejected(): void
    {
        $existing = $this->existing();
        $existing[0]['starts_at'] = '2026-10-12T18:00:00Z';
        $this->expectExceptionMessage('UTC-tider');
        $this->plan($existing);
    }

    public function testDuplicateSessionIdentityIsRejected(): void
    {
        $existing = $this->existing();
        $this->expectException(InvalidArgumentException::class);
        $this->plan([$existing[0], $existing[0]]);
    }
}
