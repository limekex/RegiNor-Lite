<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Analytics;

use InvalidArgumentException;

/** Small, explicit contract. Browser events can never assert a purchase or registration. */
final class JourneyEvent
{
    public const STAGES = ['landing', 'page_view', 'course_list', 'course_view', 'letsreg_click'];
    public const CAMPAIGN = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_content', 'utm_term'];
    public const CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'ScCid', 'li_fat_id'];
    public static function uuid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value) === 1;
    }
    public static function label(mixed $value): string
    {
        return is_string($value) && strlen($value) <= 100 && preg_match('/^[\p{L}\p{N} _.,+\-]*$/uD', $value) === 1 ? trim($value) : '';
    }
    public static function validate(array $input, bool $marketing): array
    {
        if (!self::uuid($input['event_id'] ?? null) || !self::uuid($input['journey_id'] ?? null)
            || !in_array($input['stage'] ?? '', self::STAGES, true) || ($input['statistics'] ?? false) !== true) {
            throw new InvalidArgumentException('Invalid journey event.');
        }
        $result = array_intersect_key($input, array_flip(['event_id', 'journey_id', 'stage']));
        foreach (['course_id', 'page_id'] as $key) {
            $id = $input[$key] ?? 0;
            if (!is_int($id) || $id < 0 || $id > 2147483647) { throw new InvalidArgumentException('Invalid content reference.'); }
            $result[$key] = $id;
        }
        $campaign = is_array($input['campaign'] ?? null) ? $input['campaign'] : [];
        $result['campaign'] = [];
        foreach (self::CAMPAIGN as $key) { $result['campaign'][$key] = self::label($campaign[$key] ?? ''); }
        $result['click_ids'] = [];
        if ($marketing && ($input['marketing'] ?? false) === true && is_array($input['click_ids'] ?? null)) {
            foreach (self::CLICK_IDS as $key) {
                $value = $input['click_ids'][$key] ?? '';
                if (is_string($value) && $value !== '' && strlen($value) <= 256 && preg_match('/^[A-Za-z0-9_.\-]+$/D', $value)) { $result['click_ids'][$key] = $value; }
            }
        }
        return $result;
    }
}
