<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use RuntimeException;

final class ContentTypes
{
    public const TYPES = ['course' => 'rnl_course', 'period' => 'rnl_period', 'group' => 'rnl_group', 'venue' => 'rnl_venue', 'room' => 'rnl_room', 'level' => 'rnl_level'];
    public const META = 'rnl_state';
    private static bool $ready = false;

    public static function register(): void
    {
        if (self::$ready) {
            return;
        }
        foreach (self::TYPES as $type) {
            if (post_type_exists($type)) {
                add_action('admin_notices', static function (): void {
                    if (current_user_can('manage_options')) {
                        echo '<div class="notice notice-error"><p>' . esc_html__('RegiNor Lite kan ikke starte lagringen: et innholdstypenavn er allerede i bruk. Eksisterende innhold er ikke endret.', 'reginor-lite') . '</p></div>';
                    }
                });
                return;
            }
        }
        $labels = ['course' => __('Kursbeskrivelser', 'reginor-lite'), 'period' => __('Kursperioder', 'reginor-lite'), 'group' => __('Kurs', 'reginor-lite'), 'venue' => __('Kurssteder', 'reginor-lite'), 'room' => __('Saler', 'reginor-lite'), 'level' => __('Kursnivåer', 'reginor-lite')];
        foreach (self::TYPES as $kind => $type) {
            register_post_type($type, [
                'label' => $labels[$kind], 'public' => false, 'publicly_queryable' => false,
                'exclude_from_search' => true, 'show_ui' => false, 'show_in_rest' => false,
                'rewrite' => false, 'query_var' => false, 'can_export' => false, 'has_archive' => false,
                'supports' => ['title', 'custom-fields'],
                'capability_type' => [$type, $type . 's'], 'map_meta_cap' => true,
                'capabilities' => self::capabilities($kind),
                'delete_with_user' => false,
            ]);
            register_post_meta($type, self::META, [
                'type' => 'object', 'single' => true,
                // Schema is ready for future controlled routes. No CPT REST routes are exposed.
                'show_in_rest' => ['schema' => StateSchema::envelope($kind)],
                // Aggregate changes must use repository version/preview controls, never raw REST meta.
                'auth_callback' => '__return_false',
                'sanitize_callback' => static function ($value) use ($kind): array {
                    $valid = rest_validate_value_from_schema($value, StateSchema::envelope($kind), self::META);
                    if (is_wp_error($valid)) {
                        throw new \InvalidArgumentException(__('Ugyldig lagringsformat: ', 'reginor-lite') . $valid->get_error_message());
                    }
                    $value['data'] = StateSchema::validate($kind, $value['data']);
                    return $value;
                },
            ]);
        }
        self::$ready = true;
        self::upgradeAdministratorCapabilities();
        Roles::upgrade();
    }

    private static function upgradeAdministratorCapabilities(): void
    {
        if ((int) get_option('rnl_capability_version', 0) >= 3) {
            return;
        }
        $administrator = get_role('administrator');
        if (!$administrator) {
            return;
        }
        foreach (self::TYPES as $type) {
            foreach ((array) get_post_type_object($type)->cap as $cap) {
                if ($cap !== 'read' && $cap !== 'do_not_allow') {
                    $administrator->add_cap($cap);
                }
            }
        }
        update_option('rnl_capability_version', 3, false);
    }

    private static function capabilities(string $kind): array
    {
        $caps = ['read' => 'read'];
        foreach (['edit_post' => 'edit', 'read_post' => 'read', 'delete_post' => 'delete'] as $key => $verb) {
            $caps[$key] = 'rnl_' . $verb . '_' . $kind;
        }
        foreach (['edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts',
            'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts'] as $key) {
            $caps[$key] = 'rnl_' . str_replace('posts', $kind . 's', $key);
        }
        return $caps;
    }

    public static function type(string $kind): string
    {
        if (!self::$ready || !isset(self::TYPES[$kind])) {
            throw new RuntimeException(__('RegiNors lagring er ikke tilgjengelig for denne innholdstypen.', 'reginor-lite'));
        }
        return self::TYPES[$kind];
    }
}
