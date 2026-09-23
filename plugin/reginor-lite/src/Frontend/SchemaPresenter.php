<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\RichText;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

final class SchemaPresenter
{
    public static function group(array $g, string $url): array
    {
        $instance = ['@type' => 'CourseInstance', '@id' => $url . '#instance', 'url' => $url, 'name' => $g['title'],
            'description' => RichText::plain($g['description']), 'courseMode' => 'Onsite', 'inLanguage' => str_replace('_', '-', get_locale()),
            'eventStatus' => 'https://schema.org/' . ($g['status'] === 'cancelled' ? 'EventCancelled' : 'EventScheduled'),
            'location' => self::place($g),
            'instructor' => array_map(static fn ($name) => ['@type' => 'Person', 'name' => $name], $g['instructors'])];
        if ($g['first'] && $g['last']) { $instance['startDate'] = $g['first']; $instance['endDate'] = $g['last']; }
        $instance['courseSchedule'] = [];
        foreach ($g['sessions'] as $session) {
            if ($session['status'] === 'cancelled') { continue; }
            $instance['courseSchedule'][] = ['@type' => 'Schedule', 'startDate' => $session['date'], 'endDate' => $session['date'],
                'startTime' => $session['start_time'], 'endTime' => $session['end_time'], 'scheduleTimezone' => $session['timezone']];
        }
        $instance['subEvent'] = array_map(static function (array $session) use ($g, $url): array {
            $status = $g['status'] === 'cancelled' || $session['status'] === 'cancelled' ? 'EventCancelled' : ($session['status'] === 'moved' ? 'EventRescheduled' : 'EventScheduled');
            $event = ['@type' => 'Event', '@id' => $url . '#session-' . rawurlencode((string) ($session['id'] ?? $session['original_date'])), 'url' => $url, 'name' => $g['title'] . __(' – kurskveld', 'reginor-lite'), 'startDate' => $session['starts_at'], 'endDate' => $session['ends_at'],
                'eventStatus' => 'https://schema.org/' . $status,
                'location' => self::place($session)];
            if ($session['status'] === 'moved' && $session['date'] !== $session['original_date']) { $event['previousStartDate'] = $session['original_date']; }
            return $event;
        }, $g['sessions']);
        if ($g['registration_url'] && in_array($g['status'], ['available', 'external', 'waiting'], true)) {
            $instance['offers'] = ['@type' => 'Offer', 'url' => $g['registration_url'], 'price' => number_format($g['price_minor'] / 100, 2, '.', ''),
                'priceCurrency' => 'NOK', 'description' => __('Hele kurset, ', 'reginor-lite') . ($g['price_basis'] === 'pair' ? __('per par. ', 'reginor-lite') : __('per person. ', 'reginor-lite')) . RichText::plain($g['price_terms'])];
            if (!empty($g['price_from'])) {
                // A minimum is not an exact offer price. Keep its wording without asserting a fixed charge.
                unset($instance['offers']['price']);
                $instance['offers']['description'] = sprintf(/* translators: %s: minimum course price in NOK. */ __('Fra %s kr. ', 'reginor-lite'), number_format($g['price_minor'] / 100, 2, ',', ' ')) . $instance['offers']['description'];
            }
            // Registration availability does not prove bookable stock across shared pools.
        }
        return (!empty($g['level_name']) ? ['educationalLevel' => $g['level_name']] : []) + ['@context' => 'https://schema.org', '@type' => 'Course', '@id' => $url . '#course', 'name' => $g['title'],
            'description' => RichText::plain($g['description']), 'coursePrerequisites' => RichText::plain($g['level']), 'hasCourseInstance' => $instance];
    }

    public static function listing(array $groups, string $url): array
    {
        $items = []; foreach (array_values($groups) as $i => $g) { $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $g['title'], 'url' => PublicSite::url($g['id'])]; }
        return ['@context' => 'https://schema.org', '@type' => 'CollectionPage', '@id' => $url . '#collection', 'url' => $url, 'name' => __('Kursoversikt', 'reginor-lite'),
            'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $items]];
    }

    private static function place(array $data): array
    {
        $place = ['@type' => 'Place', 'name' => $data['venue'] . ' – ' . $data['room']];
        // Keep the original address as text; do not guess city/postcode from an unstructured line.
        if ($data['address'] !== '') { $place['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $data['address']]; }
        if (isset($data['latitude'], $data['longitude'])) { $place['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $data['latitude'], 'longitude' => $data['longitude']]; }
        return $place;
    }

    public static function script(array $schema): string
    {
        return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}
