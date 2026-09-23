<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\CourseCalendar;

final class CalendarEndpoint
{
    public static function boot(): void
    {
        add_action('template_redirect', [self::class, 'serve'], -20);
    }

    public static function response(array $query): array
    {
        $identity = $query['rnl_calendar'] ?? null; $language = $query['rnl_calendar_language'] ?? 'default';
        $id = is_string($identity) ? CourseCalendar::find($identity) : null;
        $calendar = $id && is_string($language) ? CourseCalendar::read($id, $language) : null;
        if (!$calendar) { return ['status' => 404, 'headers' => ['Content-Type' => 'text/plain; charset=utf-8'], 'body' => 'Not found.']; }
        $download = ($query['rnl_calendar_download'] ?? '') === '1';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="kursdatoer-' . $calendar['identity'] . '.ics"'], 'body' => $calendar['ics']];
    }

    public static function serve(): void
    {
        if (!array_key_exists('rnl_calendar', $_GET)) { return; }
        PublicSite::noCache();
        header('X-Robots-Tag: noindex, nofollow, noarchive'); header('X-Content-Type-Options: nosniff');
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { status_header(405); header('Allow: GET, HEAD'); exit; }
        try { $response = self::response(wp_unslash($_GET)); }
        catch (\Throwable) { $response = ['status' => 503, 'headers' => ['Content-Type' => 'text/plain; charset=utf-8', 'Retry-After' => '60'], 'body' => 'Temporarily unavailable.']; }
        status_header($response['status']);
        foreach ($response['headers'] as $name => $value) { header($name . ': ' . $value); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { echo $response['body']; }
        exit;
    }
}
