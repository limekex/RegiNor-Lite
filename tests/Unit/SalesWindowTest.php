<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\Publication\SalesWindow;
use RegiNor\Lite\Domain\Publication\SalesStatus;

final class SalesWindowTest extends TestCase
{
    public function testCourseCannotExpandOrConfigurePeriodWindow(): void
    {
        $period = ['sales_from' => '2030-01-02T10:00:00Z', 'sales_until' => '2030-01-06T10:00:00Z'];
        $course = ['registration_from' => '2030-01-01T10:00:00Z', 'registration_until' => '2030-01-08T10:00:00Z'];
        [$from, $until] = SalesWindow::bounds($period, $course);
        self::assertEquals(new DateTimeImmutable($period['sales_from']), $from);
        self::assertEquals(new DateTimeImmutable($period['sales_until']), $until);
        self::assertSame([null, null], SalesWindow::bounds([], $course));
        self::assertEquals([$from, $until], SalesWindow::bounds($period, []));
    }

    public function testDisjointWindowsRemainClosed(): void
    {
        [$from, $until] = SalesWindow::bounds(['sales_from' => '2030-01-02T10:00:00Z', 'sales_until' => '2030-01-06T10:00:00Z'],
            ['registration_from' => '2030-01-08T10:00:00Z']);
        self::assertSame('closed', (new SalesStatus())->effective(true, false, 'available', $from, $until, new DateTimeImmutable('2030-01-04T10:00:00Z')));
    }
}
