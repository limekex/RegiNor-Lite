<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\Scheduling\ConflictValidator;
use RegiNor\Lite\Domain\Scheduling\ScheduledTime;
use RegiNor\Lite\Domain\Scheduling\SessionAllocation;

final class ConflictValidatorTest extends TestCase
{
    private function session(string $id, string $start, string $end, ?string $room, array $instructors, bool $cancelled = false): SessionAllocation
    {
        return new SessionAllocation($id, new ScheduledTime(new DateTimeImmutable($start), new DateTimeImmutable($end)), $room, $instructors, $cancelled);
    }

    public function testConflictsUseAbsoluteTimeAndActualResources(): void
    {
        $a = $this->session('a', '2026-10-26T18:00:00+01:00', '2026-10-26T19:00:00+01:00', 'venue-1/room-1', ['i1']);
        $b = $this->session('b', '2026-10-26T17:30:00Z', '2026-10-26T18:30:00Z', 'venue-1/room-1', ['i1', 'i2']);
        self::assertSame([['first' => 'a', 'second' => 'b', 'room' => 'venue-1/room-1', 'instructors' => ['i1']]], (new ConflictValidator())->find([$a, $b]));
    }

    public function testInstructorConflictsAcrossDifferentRooms(): void
    {
        $a = $this->session('period-a/session', '2026-10-12T18:00:00Z', '2026-10-12T19:00:00Z', 'room-a', ['i1']);
        $b = $this->session('period-b/session', '2026-10-12T18:30:00Z', '2026-10-12T19:30:00Z', 'room-b', ['i1']);
        $conflicts = (new ConflictValidator())->find([$a, $b]);
        self::assertCount(1, $conflicts);
        self::assertNull($conflicts[0]['room']);
        self::assertSame(['i1'], $conflicts[0]['instructors']);
    }

    public function testSameRoomConflictsEvenWithDifferentInstructors(): void
    {
        $a = $this->session('a', '2026-10-12T18:00:00Z', '2026-10-12T20:00:00Z', 'room-a', ['i1']);
        $b = $this->session('b', '2026-10-12T18:30:00Z', '2026-10-12T19:00:00Z', 'room-a', ['i2']);
        self::assertSame([['first' => 'a', 'second' => 'b', 'room' => 'room-a', 'instructors' => []]], (new ConflictValidator())->find([$a, $b]));
    }

    public function testAdjacentSeparateAndCancelledSessionsAreAllowed(): void
    {
        $a = $this->session('a', '2026-10-12T18:00:00Z', '2026-10-12T19:00:00Z', 'room-a', ['i1']);
        $adjacent = $this->session('b', '2026-10-12T19:00:00Z', '2026-10-12T20:00:00Z', 'room-a', ['i1']);
        $separate = $this->session('c', '2026-10-12T18:00:00Z', '2026-10-12T19:00:00Z', 'room-b', ['i2']);
        $cancelled = $this->session('d', '2026-10-12T18:00:00Z', '2026-10-12T19:00:00Z', 'room-a', ['i1'], true);
        self::assertSame([], (new ConflictValidator())->find([$a, $adjacent, $separate, $cancelled]));
    }

    public function testUnknownRoomsAreNotTheSameRoom(): void
    {
        $a = $this->session('a', '2026-10-12T18:00:00Z', '2026-10-12T19:00:00Z', null, []);
        $b = $this->session('b', '2026-10-12T18:00:00Z', '2026-10-12T19:00:00Z', null, []);
        self::assertSame([], (new ConflictValidator())->find([$a, $b]));
    }

    public function testDuplicateIdentitiesAreRejected(): void
    {
        $a = $this->session('a', '2026-10-12T18:00:00Z', '2026-10-12T19:00:00Z', 'room-a', []);
        $this->expectException(InvalidArgumentException::class);
        (new ConflictValidator())->find([$a, $a]);
    }
}
