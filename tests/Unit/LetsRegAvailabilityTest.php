<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RegiNor\Lite\Domain\Publication\LetsRegAvailability as Availability;

final class LetsRegAvailabilityTest extends TestCase
{
    public static function cases(): iterable
    {
        yield 'open' => [[], [], 'available', true];
        yield 'zero registered is not full' => [['registeredParticipants' => 0, 'maxAllowedRegistrations' => 20, 'availableRegistrations' => 20], ['registered' => 0, 'available' => 20], 'available', true];
        yield 'not yet' => [['registrationStartDate' => '2030-01-02T13:00:00+01:00'], [], 'later', false];
        yield 'closed boundary' => [['registrationEndDate' => '2030-01-01T13:00:00+01:00'], [], 'closed', false];
        yield 'local wall time' => [['registrationStartDate' => '2030-01-01T13:00:00'], [], 'available', true];
        yield 'invalid date' => [['registrationStartDate' => '2030-02-30T12:00:00'], [], 'unknown', false];
        yield 'reversed' => [['registrationStartDate' => '2030-03-01T12:00:00Z'], [], 'unknown', false];
        yield 'no upper bound' => [['registrationEndDate' => null], [], 'available', true];
        yield 'cancelled' => [['isCancelled' => true], [], 'cancelled', false];
        yield 'unpublished' => [['published' => false], [], 'closed', false];
        yield 'inactive' => [['active' => false], [], 'closed', false];
        yield 'archived' => [['isArchived' => true], [], 'closed', false];
        yield 'invalid archive flag' => [['isArchived' => null], [], 'unknown', false];
        yield 'event full' => [['availableRegistrations' => 0, 'maxAllowedRegistrations' => 20, 'registeredParticipants' => 20], [], 'full', true];
        yield 'waiting' => [['availableRegistrations' => 0, 'maxAllowedRegistrations' => 20, 'registeredParticipants' => 20, 'hasWaitinglist' => true], [], 'waiting', true];
        yield 'over limit remains full' => [['maxAllowedRegistrations' => 6, 'registeredParticipants' => 7], [], 'full', true];
        yield 'zero reported but below limit' => [['availableRegistrations' => 0, 'maxAllowedRegistrations' => 6, 'registeredParticipants' => 5], [], 'available', true];
        yield 'missing registered is not zero' => [['availableRegistrations' => 0, 'maxAllowedRegistrations' => 6], [], 'available', false];
        yield 'unlimited event with known category' => [['availableRegistrations' => 0, 'maxAllowedRegistrations' => 0, 'registeredParticipants' => 0], [], 'available', true];
        yield 'unlimited event with registrations' => [['availableRegistrations' => 0, 'maxAllowedRegistrations' => 0, 'registeredParticipants' => 4], [], 'available', true];
        yield 'unknown event' => [['availableRegistrations' => -1], [], 'available', false];
        yield 'nullable event' => [['availableRegistrations' => null], [], 'available', false];
        yield 'unknown category' => [[], ['available' => -1], 'available', false];
        yield 'boolean count' => [[], ['available' => false], 'available', false];
        yield 'unlimited category' => [[], ['available' => 0, 'registered' => 4], 'available', true];
        yield 'zero without event limit' => [['availableRegistrations' => 0], [], 'available', false];
        yield 'unlimited event and category' => [['availableRegistrations' => 0, 'maxAllowedRegistrations' => 0], ['available' => 0, 'registered' => 0], 'available', true];
        yield 'inactive category' => [[], ['active' => false], 'closed', false];
        yield 'category opens later' => [[], ['availableFrom' => '2030-01-02T12:00:00Z'], 'later', false];
        yield 'category expired' => [[], ['availableTill' => '2030-01-01T12:00:00Z'], 'closed', false];
        yield 'bad category date' => [[], ['availableTill' => 'bad'], 'unknown', false];
    }
    private static function event(): array
    {
        return ['active' => true, 'published' => true, 'isCancelled' => false, 'isArchived' => false, 'hasWaitinglist' => false,
            'availableRegistrations' => 10, 'registrationStartDate' => '2029-12-01T12:00:00Z', 'registrationEndDate' => '2030-02-01T12:00:00Z'];
    }
    private static function price(int $id = 1): array { return ['id' => $id, 'active' => true, 'available' => 3, 'availableFrom' => null, 'availableTill' => null]; }
    private static function mapping(): array { return ['categories' => [['id' => 1, 'role' => 'leader', 'registration' => 'single']]]; }
    private static function view(array $event, array $prices, ?array $mapping = null): array
    {
        return Availability::project(Availability::observation($event, $prices), $mapping ?? self::mapping(), 'Europe/Oslo', new DateTimeImmutable('2030-01-01T12:00:00Z'));
    }
    #[DataProvider('cases')]
    public function testStatus(array $event, array $price, string $status, bool $known): void
    {
        $view = self::view(array_replace(self::event(), $event), [array_replace(self::price(), $price)]);
        self::assertSame($status, $view['status']); self::assertSame($known, $view['capacity_known']);
    }
    public function testOnlyMappedCategoriesAndCompleteWindows(): void
    {
        self::assertSame('available', self::view(self::event(), [self::price(), array_replace(self::price(2), ['available' => 0])])['status']);
        self::assertSame('unknown', self::view(self::event(), [self::price(2)])['status']);
        $event = self::event(); unset($event['registrationStartDate']);
        self::assertSame('unknown', self::view($event, [self::price()])['status']);
        $view = self::view(self::event(), [array_replace(self::price(), ['availableFrom' => '2030-01-02T12:00:00Z'])]);
        self::assertCount(3, $view['boundaries']);
        self::assertSame('unknown', Availability::project(null, self::mapping(), 'Europe/Oslo', new DateTimeImmutable())['status']);
    }
    public function testPairDoesNotDoubleSharedCapacity(): void
    {
        $pair = ['categories' => [['id' => 1, 'role' => 'leader', 'registration' => 'pair'], ['id' => 2, 'role' => 'follower', 'registration' => 'pair']]];
        $prices = [self::price(), self::price(2)];
        self::assertSame('full', self::view(array_replace(self::event(), ['availableRegistrations' => 1]), $prices, $pair)['status']);
        $view = self::view(self::event(), $prices, $pair);
        self::assertSame('available', $view['status']); self::assertFalse($view['capacity_known']);
        self::assertSame('available', self::view(self::event(), [self::price()], $pair)['status']);
    }
}
