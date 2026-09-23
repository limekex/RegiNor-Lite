<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class LocalDateTime
{
    public static function date(string $value): DateTimeImmutable
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || (int) substr($value, 0, 4) < 1) {
            throw new InvalidArgumentException(__('Dato må være en gyldig dato på formen ÅÅÅÅ-MM-DD.', 'reginor-lite'));
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(__('Datoen finnes ikke i kalenderen.', 'reginor-lite'));
        }
        return $date;
    }

    public static function validateTime(string $value): void
    {
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value)) {
            throw new InvalidArgumentException(__('Klokkeslett må være på formen TT:MM, fra 00:00 til 23:59.', 'reginor-lite'));
        }
    }

    public static function timezone(string $value): DateTimeZone
    {
        if (!in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException(__('Velg en gyldig navngitt tidssone, for eksempel Europe/Oslo.', 'reginor-lite'));
        }
        return new DateTimeZone($value);
    }

    /** Reject missing or ambiguous wall times instead of silently choosing an offset. */
    public static function at(string $date, string $time, string $timezone): DateTimeImmutable
    {
        self::date($date);
        self::validateTime($time);
        $zone = self::timezone($timezone);
        $wall = $date . ' ' . $time;
        $timestamp = (new DateTimeImmutable($wall, new DateTimeZone('UTC')))->getTimestamp();
        $transitions = $zone->getTransitions($timestamp - 172800, $timestamp + 172800);
        $matches = [];
        foreach (array_unique(array_column($transitions ?: [], 'offset')) as $offset) {
            $candidate = (new DateTimeImmutable('@' . ($timestamp - $offset)))->setTimezone($zone);
            if ($candidate->format('Y-m-d H:i') === $wall) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidArgumentException(__('Klokkeslettet finnes ikke eller forekommer to ganger ved tidsskiftet. Velg et entydig tidspunkt.', 'reginor-lite'));
        }
        return $matches[0];
    }
}
