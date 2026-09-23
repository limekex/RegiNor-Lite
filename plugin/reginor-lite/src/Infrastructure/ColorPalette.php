<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

final class ColorPalette
{
    public static function avada(): bool { return strtolower(get_template()) === 'avada' || class_exists('Avada'); }
    public static function choices(): array
    {
        $result = [];
        foreach ((array) wp_get_global_settings(['color', 'palette']) as $origin => $colors) {
            if ($origin === 'default' && wp_get_global_settings(['color', 'defaultPalette']) === false) { continue; }
            foreach (is_array($colors) ? $colors : [] as $entry) {
                if (!is_array($entry) || !preg_match('/^[a-z0-9-]+$/D', $entry['slug'] ?? '')) { continue; }
                $result['wp:' . $entry['slug']] = ['label' => 'WordPress · ' . ($entry['name'] ?? $entry['slug']), 'color' => $entry['color'] ?? ''];
            }
        }
        if (self::avada()) {
            for ($i = 1; $i <= 8; $i++) { $result['avada:color' . $i] = ['label' => sprintf(/* translators: %d: Avada global color number. */ __('Avada · Farge %d', 'reginor-lite'), $i), 'color' => '']; }
        }
        return $result;
    }
    public static function source(mixed $source): string
    {
        if (!is_string($source) || ($source !== '' && !preg_match('/^(?:wp:[a-z0-9-]{1,80}|avada:color[1-9][0-9]{0,3})$/D', $source))) { throw new \InvalidArgumentException(__('Velg en global farge fra listen eller egen farge.', 'reginor-lite')); }
        return $source;
    }
    public static function expression(string $hex, string $source, int $alpha): string
    {
        $hex = Appearance::color($hex); $source = self::source($source); $alpha = self::alpha($alpha);
        $base = $source === '' ? $hex : (str_starts_with($source, 'wp:') ? 'var(--wp--preset--color--' . substr($source, 3) . ',' . $hex . ')' : 'var(--awb-' . substr($source, 6) . ',' . $hex . ')');
        return $alpha === 100 ? $base : 'color-mix(in srgb,' . $base . ' ' . $alpha . '%,transparent)';
    }
    public static function alpha(mixed $value): int
    {
        if ((!is_int($value) && !(is_string($value) && preg_match('/^[0-9]{1,3}$/D', $value))) || (int) $value < 0 || (int) $value > 100) { throw new \InvalidArgumentException(__('Alpha må være mellom 0 og 100 prosent.', 'reginor-lite')); }
        return (int) $value;
    }
    public static function tone(mixed $value): string
    {
        if (!in_array($value, ['auto', 'dark', 'light'], true)) { throw new \InvalidArgumentException(__('Velg automatisk, mørk eller lys tekst.', 'reginor-lite')); }
        return $value;
    }
    public static function boot(): void
    {
        // Read actual theme CSS on the frontend, including extra Avada palette slots.
        // No private/undocumented Avada option structures or theme files are read.
        add_action('wp_enqueue_scripts', static function (): void {
            $nonce = $_GET['rnl_palette'] ?? '';
            if (!is_string($nonce) || !$nonce || !current_user_can('manage_options') || !wp_verify_nonce($nonce, 'rnl_palette')) { return; }
            \RegiNor\Lite\Frontend\PublicSite::noCache();
            wp_enqueue_script('rnl-palette-bridge', plugins_url('assets/palette-bridge.js', dirname(__DIR__, 2) . '/reginor-lite.php'), [], '0.1.0', true);
        });
    }
}
