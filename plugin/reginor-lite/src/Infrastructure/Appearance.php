<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Presentation only: independent of publication, prices and scheduling. */
final class Appearance
{
    public const OPTION = 'rnl_appearance';
    public const META = '_rnl_appearance';
    public const COLORS = ['background' => '#fcfaf7', 'day' => '#fcfaf7', 'day_heading' => '#f1e9e4', 'room' => '#f1e9e4', 'course' => '#ffffff', 'highlight' => '#fff2cf'];
    public static function color(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^#[a-f0-9]{6}$/iD', $value)) { throw new \InvalidArgumentException(__('Velg en gyldig farge, for eksempel #F1E9E4.', 'reginor-lite')); }
        return strtolower($value);
    }
    public static function ink(string $color): string
    {
        $rgb = array_map(static function ($hex): float { $v = hexdec($hex) / 255; return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; }, str_split(substr(self::color($color), 1), 2));
        $luminance = 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
        return $luminance > 0.179 ? '#000000' : '#ffffff';
    }
    public static function validate(array $raw): array
    {
        $result = [];
        $scope = $raw['day_color_views'] ?? 'week';
        if (!in_array($scope, ['week', 'list', 'both'], true)) { throw new \InvalidArgumentException(__('Velg hvor dagsfargene skal brukes.', 'reginor-lite')); }
        $result['day_color_views'] = $scope;
        foreach (self::COLORS as $key => $fallback) { $result[$key] = self::color($raw[$key] ?? $fallback); $result[$key . '_alpha'] = ColorPalette::alpha($raw[$key . '_alpha'] ?? 100); $result[$key . '_source'] = ColorPalette::source($raw[$key . '_source'] ?? ''); $result[$key . '_tone'] = ColorPalette::tone($raw[$key . '_tone'] ?? 'auto'); }
        if (!in_array($raw['view'] ?? 'period', ['period', 'list', 'week'], true) || !in_array($raw['layout'] ?? 'columns', ['columns', 'stacked'], true)) { throw new \InvalidArgumentException(__('Velg en av visningene i listen.', 'reginor-lite')); }
        $days = $raw['days'] ?? [];
        if (!is_array($days) || array_diff(array_keys($days), range(1, 7))) { throw new \InvalidArgumentException(__('Velg en gyldig ukedag.', 'reginor-lite')); }
        $result['days'] = [];
        foreach ($days as $day => $choice) {
            if (!is_array($choice)) { throw new \InvalidArgumentException(__('Kontroller dagens fargevalg.', 'reginor-lite')); }
            if (empty($choice['enabled'])) { continue; }
            $result['days'][$day] = ['enabled' => true, 'color' => self::color($choice['color'] ?? '#fcfaf7'), 'source' => ColorPalette::source($choice['source'] ?? ''), 'alpha' => ColorPalette::alpha($choice['alpha'] ?? 100), 'tone' => ColorPalette::tone($choice['tone'] ?? 'auto')];
        }
        return $result + ['view' => $raw['view'] ?? 'period', 'layout' => $raw['layout'] ?? 'columns'];
    }
    public static function settings(): array
    {
        try { $raw = get_option(self::OPTION, []); return self::validate(is_array($raw) ? $raw : []); }
        catch (\InvalidArgumentException) { return self::validate([]); }
    }
    public static function variables(?array $settings = null): string
    {
        $s = $settings ?? self::settings(); $css = '';
        foreach (self::COLORS as $key => $fallback) { $color = self::color($s[$key] ?? $fallback); $tone = $s[$key . '_tone'] ?? 'auto'; $css .= '--rnl-' . str_replace('_', '-', $key) . '-bg:' . ColorPalette::expression($color, $s[$key . '_source'] ?? '', $s[$key . '_alpha'] ?? 100) . ';--rnl-' . str_replace('_', '-', $key) . '-ink:' . ($tone === 'auto' ? self::ink($color) : ($tone === 'light' ? '#ffffff' : '#000000')) . ';'; }
        return $css;
    }
    public static function overrideStyle(string $key, array $choice): string
    {
        if (empty($choice['enabled'])) { return ''; }
        $color = self::color($choice['color']); $tone = ColorPalette::tone($choice['tone'] ?? 'auto');
        return '--rnl-' . $key . '-bg:' . ColorPalette::expression($color, $choice['source'] ?? '', $choice['alpha'] ?? 100) . ';--rnl-' . $key . '-ink:' . ($tone === 'auto' ? self::ink($color) : ($tone === 'light' ? '#ffffff' : '#000000')) . ';';
    }
    public static function dayStyle(int $day): string
    {
        if (!self::dayColorsApply('week')) { return ''; }
        $choice = self::settings()['days'][$day] ?? [];
        return self::overrideStyle('day', $choice) . self::overrideStyle('day-heading', $choice);
    }
    public static function dayColorsApply(string $view): bool
    {
        $scope = self::settings()['day_color_views'];
        return $scope === 'both' || $scope === $view;
    }
    public static function dayCardStyle(int $day): string
    {
        if (!self::dayColorsApply('list')) { return ''; }
        $s = self::settings();
        $choice = $s['days'][$day] ?? ['enabled' => true, 'color' => $s['day'], 'source' => $s['day_source'], 'alpha' => $s['day_alpha'], 'tone' => $s['day_tone']];
        return self::overrideStyle('course', $choice);
    }
    public static function roomStyle(int $id): string
    {
        $state = get_post_meta($id, ContentTypes::META, true); $d = is_array($state) ? ($state['data'] ?? []) : [];
        return self::overrideStyle('room', ['enabled' => $d['appearance_custom'] ?? false, 'color' => $d['appearance_color'] ?? '#f1e9e4', 'source' => $d['appearance_source'] ?? '', 'alpha' => $d['appearance_alpha'] ?? 100, 'tone' => $d['appearance_tone'] ?? 'auto']);
    }
    public static function course(int $id): array
    {
        $state = get_post_meta($id, ContentTypes::META, true); $d = is_array($state) ? ($state['data'] ?? []) : [];
        $raw = array_key_exists('appearance_custom', $d) ? ['color' => !empty($d['appearance_custom']) ? ($d['appearance_color'] ?? '') : '', 'source' => $d['appearance_source'] ?? '', 'alpha' => $d['appearance_alpha'] ?? 100, 'tone' => $d['appearance_tone'] ?? 'auto', 'featured' => $d['featured'] ?? false] : get_post_meta($id, self::META, true);
        $color = ''; try { if (is_array($raw) && !empty($raw['color'])) { $color = self::color($raw['color']); } } catch (\InvalidArgumentException) { /* Fall back to global color. */ }
        $extra = ['alpha' => 100, 'source' => '', 'tone' => 'auto'];
        try { if (is_array($raw)) { $extra = ['alpha' => ColorPalette::alpha($raw['alpha'] ?? 100), 'source' => ColorPalette::source($raw['source'] ?? ''), 'tone' => ColorPalette::tone($raw['tone'] ?? 'auto')]; } } catch (\InvalidArgumentException) {}
        return $extra + ['color' => $color, 'featured' => is_array($raw) && ($raw['featured'] ?? false) === true];
    }
    public static function courseStyle(int $id): string
    {
        $c = self::course($id);
        return $c['color'] ? '--rnl-course-bg:' . ColorPalette::expression($c['color'], $c['source'], $c['alpha']) . ';--rnl-course-ink:' . ($c['tone'] === 'auto' ? self::ink($c['color']) : ($c['tone'] === 'light' ? '#ffffff' : '#000000')) . ';' : '';
    }
}
