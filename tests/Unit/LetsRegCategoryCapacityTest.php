<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\Capacity\LetsRegCategoryCapacity as Categories;
use RegiNor\Lite\Domain\Publication\LetsRegAvailability as Availability;

final class LetsRegCategoryCapacityTest extends TestCase
{
    private function rows(array $counts = [6, 0], mixed $total = 20, string $registration = 'single', array $changes = []): array
    {
        $event = array_replace(['active' => true, 'published' => true, 'isCancelled' => false, 'isArchived' => false, 'hasWaitinglist' => false,
            'maxAllowedRegistrations' => 20, 'registeredParticipants' => is_int($total) && $total >= 0 && $total <= 20 ? 20 - $total : null,
            'availableRegistrations' => $total, 'registrationStartDate' => null, 'registrationEndDate' => null], $changes);
        $prices = $mapping = [];
        foreach ($counts as $id => $count) {
            $prices[] = ['id' => $id + 1, 'active' => true, 'available' => $count, 'availableFrom' => null, 'availableTill' => null];
            $mapping[] = ['id' => $id + 1, 'name' => 'Category ' . $id, 'role' => $id === 0 ? 'leader' : 'follower', 'registration' => $registration];
        }
        return Categories::project(Availability::observation($event, $prices), ['categories' => $mapping], 'Europe/Oslo', new DateTimeImmutable('2030-01-01T12:00:00Z'));
    }
    public function testCategoriesDoNotInheritOrSumEventCapacity(): void
    {
        $rows = $this->rows();
        self::assertSame([6, 20], array_column($rows, 'available'));
        self::assertSame([6, 0], array_column($rows, 'reported_available'));
        self::assertSame(['available', 'available'], array_column($rows, 'status'));
        self::assertSame([3, 3], array_column($this->rows([6, 8], 3), 'available'));
        self::assertSame([6, 8], array_column($this->rows([6, 8], 3), 'reported_available'));
        self::assertSame([0, 0], array_column($this->rows([6, 8], 0), 'available'));
    }
    public function testUnknownCountersNeverBecomeFullOrUnlimited(): void
    {
        foreach ([null, -1, true, '6', 2147483648] as $unknown) {
            self::assertSame('unknown', $this->rows([$unknown])[0]['status']);
            self::assertNull($this->rows([$unknown])[0]['available']);
            self::assertSame([null, null], array_column($this->rows([6, 8], $unknown), 'available'));
        }
        self::assertSame('unknown', $this->rows([0], null)[0]['status']);
        self::assertSame([null, null], array_column($this->rows([0, 0], 0, 'single', ['maxAllowedRegistrations' => 0]), 'available'));
        self::assertSame([0, 0], array_column($this->rows([0, 0], 0), 'available'));
    }
    public function testPairPlacesArePerParticipantAndBoundedByPartnerAndTotal(): void
    {
        self::assertSame([2, 2], array_column($this->rows([6, 2], 20, 'pair'), 'available'));
        self::assertSame([1, 1], array_column($this->rows([6, 8], 3, 'pair'), 'available'));
        self::assertSame([0, 0], array_column($this->rows([6, 8], 1, 'pair'), 'available'));
        self::assertSame([6, 6], array_column($this->rows([6, 0], 20, 'pair'), 'available'));
        self::assertSame([null], array_column($this->rows([6], 20, 'pair'), 'available'));
        self::assertSame([null, null], array_column($this->rows([6, null], 20, 'pair'), 'available'));
        self::assertSame([3, 2, 3], array_column($this->rows([6, 2, 3], 20, 'pair'), 'available')); // Alternative partner categories are never added.
    }
    public function testZeroPlaceLimitDoesNotRestrictCategoriesOrPairs(): void
    {
        self::assertSame([6, 8], array_column($this->rows([6, 8], 0, 'single', ['maxAllowedRegistrations' => 0, 'registeredParticipants' => 0]), 'available'));
        self::assertSame([2, 2], array_column($this->rows([6, 2], 0, 'pair', ['maxAllowedRegistrations' => 0, 'registeredParticipants' => 4]), 'available'));
        self::assertSame([1, 1], array_column($this->rows([6, 8], 0, 'single', ['maxAllowedRegistrations' => 6, 'registeredParticipants' => 5]), 'available'));
        self::assertSame([0, 0], array_column($this->rows([6, 8], 9, 'single', ['maxAllowedRegistrations' => 6, 'registeredParticipants' => 6]), 'available'));
        self::assertSame([0, 0], array_column($this->rows([6, 8], 9, 'single', ['maxAllowedRegistrations' => 6, 'registeredParticipants' => 7]), 'available'));
    }
    public function testPairDisplayUsesOneRowInWholePairsAndUnlimitedIsExplicit(): void
    {
        $rows = Categories::displayRows($this->rows([6, 2], 20, 'pair'));
        self::assertCount(1, $rows); self::assertSame('pair', $rows[0]['role']); self::assertSame(2, $rows[0]['available']);
        $rows = Categories::displayRows($this->rows([6, 8], 3, 'pair'));
        self::assertSame(1, $rows[0]['available']);
        $rows = Categories::displayRows($this->rows([0, 0], 0, 'pair', ['maxAllowedRegistrations' => 0]));
        self::assertSame('unlimited', $rows[0]['status']); self::assertNull($rows[0]['available']);
        self::assertSame('unknown', Categories::displayRows($this->rows([6], 20, 'pair'))[0]['status']);
        self::assertSame('full', Categories::displayRows($this->rows([0, 0], 0, 'pair'))[0]['status']);
        $rows = Categories::displayRows(array_merge($this->rows([3], 20), $this->rows([6, 2], 20, 'pair')));
        self::assertCount(2, $rows); self::assertSame('single', $rows[0]['registration']);
    }
    public function testNonSellingEventDoesNotAdvertiseNumbers(): void
    {
        foreach (['active' => false, 'published' => false, 'isArchived' => true, 'isCancelled' => true,
            'registrationStartDate' => '2030-02-01T12:00:00Z', 'registrationEndDate' => '2029-12-01T12:00:00Z'] as $key => $value) {
            self::assertSame([null, null], array_column($this->rows([6, 8], 20, 'single', [$key => $value]), 'available'));
        }
    }
    public function testOnlyMappedCategoriesAndSaleWindows(): void
    {
        $event = ['active' => true, 'published' => true, 'isCancelled' => false, 'isArchived' => false, 'availableRegistrations' => 10, 'registrationStartDate' => null, 'registrationEndDate' => null];
        $prices = [['id' => 1, 'active' => true, 'available' => 9, 'availableFrom' => '2030-01-02T12:00:00Z', 'availableTill' => null],
            ['id' => 2, 'active' => true, 'available' => 99, 'availableFrom' => null, 'availableTill' => null]];
        $mapping = ['categories' => [['id' => 1, 'name' => 'Mapped', 'role' => 'open', 'registration' => 'single']]];
        $rows = Categories::project(Availability::observation($event, $prices), $mapping, 'Europe/Oslo', new DateTimeImmutable('2030-01-01T12:00:00Z'));
        self::assertCount(1, $rows); self::assertSame('later', $rows[0]['status']); self::assertNull($rows[0]['available']);
        self::assertSame(9, $rows[0]['reported_available']);
        $rows = Categories::project(null, $mapping, 'Europe/Oslo', new DateTimeImmutable());
        self::assertNull($rows[0]['reported_available']); self::assertSame('unknown', $rows[0]['status']);
    }
}
