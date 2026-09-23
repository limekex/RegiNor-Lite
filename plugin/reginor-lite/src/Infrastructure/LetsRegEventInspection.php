<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

/** A minimal private identity preview, never a capacity observation or a course mapping. */
final class LetsRegEventInspection
{
    public static function search(array $data, int $organizerId, int $affiliateId): ?array
    {
        if (!array_is_list($data) || count($data) > 20) { return null; }
        $events = [];
        foreach ($data as $row) {
            if (!is_array($row) || !is_int($row['id'] ?? null) || $row['id'] < 1 || $row['id'] > 2147483647 || isset($events[$row['id']])) { return null; }
            $event = self::event($row, $row['id'], $organizerId, $affiliateId);
            if ($event === null) { return null; }
            $events[$row['id']] = $event;
        }
        return array_values($events);
    }

    public static function event(array $data, int $eventId, int $organizerId, int $affiliateId): ?array
    {
        if (($data['id'] ?? null) !== $eventId || ($data['organizer']['id'] ?? null) !== $organizerId
            || ($data['organizer']['affiliateId'] ?? null) !== $affiliateId) { return null; }
        $name = self::name($data['name'] ?? null);
        if ($name === null) { return null; }
        $event = ['id' => $eventId, 'name' => $name];
        foreach (['active', 'published', 'isCancelled'] as $key) {
            if (!is_bool($data[$key] ?? null)) { return null; }
            $event[$key] = $data[$key];
        }
        $url = self::publicUrl($data['eventUrl'] ?? null);
        if ($url !== null) { $event['event_url'] = $url; }
        // Keep a safe subset of formatting, never provider scripts or layout.
        $description = $data['description'] ?? null;
        if (is_string($description) && strlen($description) <= 100000) {
            $description = RichText::clean($description);
            if (mb_strlen($description) <= 10000) { $event['description'] = $description; }
        }
        foreach (['startDate', 'endDate', 'registrationStartDate', 'registrationEndDate', 'lastUpdate'] as $key) {
            $date = self::date($data[$key] ?? null);
            if ($date !== null) { $event[$key] = $date; }
        }
        return $event;
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,7})?(Z|[+-](?:0\d|1[0-3]):[0-5]\d|[+-]14:00)?$/D', $value, $m)) { return null; }
        return (int) $m[1] >= 1900 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    private static function publicUrl(mixed $raw): ?string
    {
        if (!is_string($raw) || $raw === '' || strlen($raw) > 2000) { return null; }
        try { $url = \RegiNor\Lite\Domain\RegistrationUrl::normalize($raw); }
        catch (\InvalidArgumentException) { return null; }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $port = wp_parse_url($url, PHP_URL_PORT);
        return in_array($host, RegistrationDomains::allowed(), true) && ($port === null || $port === 443) ? $url : null;
    }

    public static function prices(array $data): ?array
    {
        // The documented endpoint is an array without pagination. Never truncate a response.
        if (!array_is_list($data) || count($data) > 200) { return null; }
        $prices = []; $seen = [];
        foreach ($data as $row) {
            if (!is_array($row)) { return null; }
            $id = $row['id'] ?? null; $name = self::name($row['name'] ?? null);
            if (!is_int($id) || $id < 1 || $id > 2147483647 || isset($seen[$id]) || $name === null
                || !is_bool($row['active'] ?? null)) { return null; }
            $seen[$id] = true;
            $price = ['id' => $id, 'name' => $name, 'active' => $row['active']];
            $amount = $row['price'] ?? null;
            if ((is_int($amount) || is_float($amount)) && is_finite((float) $amount) && $amount >= 0 && $amount <= 1000000
                && abs($amount * 100 - round($amount * 100)) < 0.00001) { $price['price_minor'] = (int) round($amount * 100); }
            $prices[] = $price;
        }
        return $prices;
    }

    private static function name(mixed $name): ?string
    {
        // Nullable names are legal in OpenAPI. Display an explicit fallback in the UI.
        return $name === null ? '' : (is_string($name) && strlen($name) <= 500 ? sanitize_text_field($name) : null);
    }
}
