<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

final class Roles
{
    public static function boot(): void
    {
        add_action('admin_menu', static function (): void {
            if (!current_user_can('rnl_edit_periods') || current_user_can('edit_posts') || current_user_can('manage_options')) { return; }
            global $menu;
            foreach ((array) $menu as $item) {
                if (!in_array($item[2], ['reginor-lite', 'profile.php'], true)) { remove_menu_page($item[2]); }
            }
        }, 99);
        add_filter('map_meta_cap', [self::class, 'protectDeletion'], 10, 4);
        add_filter('wp_insert_post_empty_content', [self::class, 'protectGenericWrites'], 10, 2);
        add_filter('login_redirect', static function ($redirect, $requested, $user) {
            return $user instanceof \WP_User && $user->has_cap('rnl_edit_periods') && !$user->has_cap('edit_posts') && !$requested
                ? admin_url('admin.php?page=reginor-lite') : $redirect;
        }, 10, 3);
    }

    public static function upgrade(): void
    {
        if ((int) get_option('rnl_workflow_role_version', 0) >= 3) { return; }
        $role = get_role('rnl_course_manager') ?? add_role('rnl_course_manager', __('Kursansvarlig', 'reginor-lite'), ['read' => true]);
        if (!$role) { return; }
        $caps = ['read', 'rnl_select_resources', 'rnl_link_letsreg', 'rnl_import_letsreg'];
        foreach (['period', 'group'] as $kind) {
            $type = get_post_type_object(ContentTypes::type($kind));
            foreach ((array) $type->cap as $key => $cap) {
                if (!str_starts_with($key, 'delete') && $cap !== 'do_not_allow') { $caps[] = $cap; }
            }
        }
        foreach (array_unique($caps) as $cap) { $role->add_cap($cap); }
        get_role('administrator')?->add_cap('rnl_select_resources');
        update_option('rnl_workflow_role_version', 3, false);
    }

    public static function protectDeletion(array $caps, string $cap, int $userId, array $args): array
    {
        if ($cap === 'delete_post' && isset($args[0]) && in_array(get_post_type((int) $args[0]), ContentTypes::TYPES, true)
            && !user_can($userId, 'manage_options')) { return ['do_not_allow']; }
        return $caps;
    }

    public static function protectGenericWrites(bool $empty, array $post): bool
    {
        if (in_array($post['post_type'] ?? '', ContentTypes::TYPES, true) && !Mutation::active() && !current_user_can('manage_options')) {
            return true;
        }
        return $empty;
    }
}
