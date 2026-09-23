<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RegiNor\Lite\Domain\Capacity\CapacityView;
use RegiNor\Lite\Domain\Capacity\Snapshot;
use RegiNor\Lite\Domain\Capacity\Retry;
use RegiNor\Lite\Infrastructure\DemoCapacityAdapter;

final class CapacityTest extends TestCase
{
    public static function scenarios(): array
    {
        return [['unknown', 'unknown'], ['fresh', 'fresh'], ['full', 'full'], ['roles', 'roles'], ['expired', 'expired'], ['error', 'error'], ['rate_limit', 'error']];
    }

    #[DataProvider('scenarios')]
    public function testDemoNeverBecomesPublicStock(string $scenario, string $expected): void
    {
        $result = (new DemoCapacityAdapter())->fetch($scenario, 10000);
        $private = CapacityView::project($result['snapshot'] ?? null, 10000, $result['error'] ?? null, false, true);
        self::assertSame($expected, $private['state']);
        self::assertFalse($private['confirmed']);
        $public = CapacityView::project($result['snapshot'] ?? null, 10000, $result['error'] ?? null);
        self::assertSame('unknown', $public['state']);
        self::assertNull($public['expires_at']);
        self::assertArrayNotHasKey('available', $public);
    }

    public function testExactExpiryAndFailureHideLastGoodObservation(): void
    {
        $snapshot = (new DemoCapacityAdapter())->fetch('fresh', 10000)['snapshot'];
        self::assertSame('fresh', CapacityView::project($snapshot, 10899, null, true)['state']);
        $expired = CapacityView::project($snapshot, 10900, null, true);
        self::assertSame('expired', $expired['state']);
        self::assertFalse($expired['confirmed']);
        self::assertSame('error', CapacityView::project($snapshot, 10001, 'temporary', true)['state']);
        self::assertSame('expired', CapacityView::project($snapshot, 9999, null, true)['state']);
    }

    public function testRolesAreSeparateAndNeverGeneralAvailability(): void
    {
        $snapshot = (new DemoCapacityAdapter())->fetch('roles', 10000)['snapshot'];
        $view = CapacityView::project($snapshot, 10000, null, true);
        self::assertSame('roles', $view['state']);
        self::assertSame(['Fører: plasser tilgjengelig', 'Følger: fullt'], $view['roles']);
        self::assertStringNotContainsString('3', $view['label']);
        $snapshot['roles']['follower'] = null;
        self::assertStringContainsString('Følger: sjekk hos LetsReg', CapacityView::project($snapshot, 10000, null, true)['label']);
    }

    public static function invalidSnapshots(): array
    {
        return [[['complete' => false]], [['available' => -1]], [['available' => '0']], [['available' => 1.5]],
            [['roles' => ['leader' => 2]]], [['roles' => ['leader' => 2, 'follower' => 1], 'available' => 8]],
            [['roles' => ['leader' => -1, 'follower' => null], 'available' => null]],
            [['source_version' => -1]], [['expires_at' => 10000]], [['expires_at' => 10901]], [['participant_email' => 'must-not-be-stored']]];
    }

    #[DataProvider('invalidSnapshots')]
    public function testPartialInvalidAndUnexpectedDataRejected(array $change): void
    {
        $snapshot = (new DemoCapacityAdapter())->fetch('fresh', 10000)['snapshot'];
        $this->expectException(InvalidArgumentException::class);
        Snapshot::validate(array_replace($snapshot, $change));
    }

    public static function oldResults(): array { return [[9999, 11], [10001, 9], [10001, null], [11000, 11]]; }

    #[DataProvider('oldResults')]
    public function testOlderOrFutureResultsCannotReplaceNewerSnapshot(int $captured, ?int $version): void
    {
        $old = (new DemoCapacityAdapter())->fetch('fresh', 10000)['snapshot']; $old['source_version'] = 10;
        $new = array_replace($old, ['captured_at' => $captured, 'expires_at' => $captured + 900, 'source_version' => $version]);
        $this->expectException(InvalidArgumentException::class);
        Snapshot::accept($new, $old, 10500);
    }

    public function testBackoffJitterAndProviderWait(): void
    {
        self::assertSame(60, Retry::delay(1, 0));
        self::assertSame(150, Retry::delay(2, 30));
        self::assertSame(3630, Retry::delay(20, 30));
        self::assertSame(86400, Retry::delay(1, 0, 86400));
    }
}
