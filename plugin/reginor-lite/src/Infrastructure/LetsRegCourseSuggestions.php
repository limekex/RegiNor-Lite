<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use RegiNor\Lite\Domain\LocalDateTime;
use RegiNor\Lite\Domain\CourseLevelSuggestion;

/** Private editing suggestions only. Applying/saving them uses the normal local course workflow. */
final class LetsRegCourseSuggestions
{
    public static function search(array $result, array $period): array
    {
        $result['period'] = ['start' => $period['start_date'], 'end' => $period['end_date'] ?? null];
        foreach ($result['events'] as &$event) {
            $date = self::local($event['startDate'] ?? null, $period['timezone']);
            $event['start_on'] = $date?->format('Y-m-d');
            $event['start_label'] = $date?->format('d.m.Y');
        }
        unset($event);
        return $result;
    }

    public static function from(array $event, array $group, array $period, array $levels = []): array
    {
        $fields = [];
        $template = \RegiNor\Lite\Domain\LetsRegDescriptionTemplate::parse($event['description'] ?? '');
        if ($template['mode'] === 'structured') { $fields['price_terms'] = $template['fields']['price_terms']; }
        if ($event['name'] !== '' && mb_strlen($event['name']) <= 250) { $fields['title'] = $event['name']; }
        $level = CourseLevelSuggestion::fromName($event['name'], $levels);
        if ($level !== null) { $fields['level_id'] = $level; }
        $start = self::local($event['startDate'] ?? null, $group['timezone']);
        $end = self::local($event['endDate'] ?? null, $group['timezone']);
        if ($start) {
            $fields['weekday'] = $start->format('N');
            $fields['start_time'] = $start->format('H:i');
            $fields['first_date'] = $start->format('Y-m-d');
        }
        if ($start && $end && $end > $start && $end->format('H:i') > $start->format('H:i')) { $fields['end_time'] = $end->format('H:i'); }
        $from = self::local($event['registrationStartDate'] ?? null, $group['timezone']);
        $until = self::local($event['registrationEndDate'] ?? null, $group['timezone']);
        if ($from && $until && $until > $from && $event['active'] && $event['published'] && !$event['isCancelled']) {
            $fields['registration_from'] = $from->format('Y-m-d\TH:i');
            $fields['registration_until'] = $until->format('Y-m-d\TH:i');
            $fields['registration_status'] = 'automatic';
        }
        return ['fields' => $fields, 'start_date' => $start?->format('Y-m-d'), 'end_date' => $end?->format('Y-m-d'),
            'timezone' => $group['timezone'], 'period_breaks' => array_map(static fn ($b) => ['from' => $b['from'], 'until' => $b['until']], $period['breaks']),
            'prices' => array_values(array_filter($event['prices'], static fn ($p) => $p['active']))];
    }

    private static function local(?string $value, string $timezone): ?DateTimeImmutable
    {
        if ($value === null) { return null; }
        try {
            // Offset-free provider dates are proposed as local wall time, explicitly explained in the UI.
            if (!preg_match('/(?:Z|[+-]\d{2}:\d{2})$/D', $value)) { return LocalDateTime::at(substr($value, 0, 10), substr($value, 11, 5), $timezone); }
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone));
        } catch (\Throwable) { return null; }
    }
}
