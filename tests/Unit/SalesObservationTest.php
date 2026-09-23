<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\Analytics\SalesObservation as Sales;

final class SalesObservationTest extends TestCase
{
    public function testMissingAndMalformedTotalsNeverBecomeZeroOrPaidRevenue(): void
    {
        self::assertSame(['participants'=>null,'order_sum_minor'=>null],Sales::fromEvent([]));
        foreach ([['registeredParticipants'=>'4','ordersTotalSum'=>'1200'],['registeredParticipants'=>-1,'ordersTotalSum'=>INF],['registeredParticipants'=>1.5,'ordersTotalSum'=>12.345]] as $bad) {
            self::assertSame(['participants'=>null,'order_sum_minor'=>null],Sales::fromEvent($bad));
        }
        self::assertSame(['participants'=>0,'order_sum_minor'=>0],Sales::fromEvent(['registeredParticipants'=>0,'ordersTotalSum'=>0]));
        self::assertSame(['participants'=>3,'order_sum_minor'=>139050],Sales::fromEvent(['registeredParticipants'=>3,'ordersTotalSum'=>1390.50,'email'=>'ignored']));
    }
    public function testFirstMeasurementAndCorrectionsAreNotSales(): void
    {
        $first=['at'=>1000,'participants'=>2,'order_sum_minor'=>20000];
        self::assertFalse(Sales::change(null,$first)['increase']);
        $second=['at'=>1600,'participants'=>1,'order_sum_minor'=>10000];
        self::assertSame(-10000,Sales::change($first,$second)['order_sum_minor']);
        self::assertSame('no_increase',Sales::coincidence($first,$second,[],15)['reason']);
        self::assertSame('baseline',Sales::coincidence($first,$first,[],15)['reason']);
        self::assertNull(Sales::change(['at'=>1000,'participants'=>null,'order_sum_minor'=>null],$second)['participants']);
    }
    public function testCoincidenceDeduplicatesJourneysAndKeepsUnknownSourcePossible(): void
    {
        $first=['at'=>2000,'participants'=>2,'order_sum_minor'=>20000];
        $second=['at'=>2600,'participants'=>4,'order_sum_minor'=>40000];
        $click=['at'=>2200,'journey'=>'one','source'=>'google','medium'=>'cpc','campaign'=>'test'];
        $one=Sales::coincidence($first,$second,[$click,$click],15);
        self::assertSame('one_candidate',$one['reason']);self::assertSame(1,$one['journeys']);
        self::assertArrayNotHasKey('probability',$one);self::assertArrayNotHasKey('sales',$one);
        self::assertSame('multiple_candidates',Sales::coincidence($first,$second,[$click,array_replace($click,['journey'=>'two'])],15)['reason']);
        self::assertSame('no_clicks',Sales::coincidence($first,$second,[array_replace($click,['at'=>2601])],15)['reason']);
        self::assertSame('gap',Sales::coincidence($first,array_replace($second,['at'=>3300]),[$click],15)['reason']);
        $early=array_replace($click,['at'=>1500]);
        self::assertSame('no_clicks',Sales::coincidence($first,$second,[$early],5)['reason']);
        self::assertSame('one_candidate',Sales::coincidence($first,$second,[$early],15)['reason']);
    }
    public function testWindowIsBounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Sales::coincidence(null,['at'=>1000],[],999);
    }
}
