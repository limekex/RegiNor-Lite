<?php
/**
 * Read-only structure inventory. Run with: wp eval-file /path/to/inspect-wordpress.php
 * Does not export post content, field values, options dumps, users or credentials.
 * No strict_types declaration: WP-CLI eval-file evaluates a wrapped script.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Kjør med wp eval-file i WordPress-installasjonen som skal kartlegges.\n");
    }
    exit(1);
}

(static function (): void {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $theme = wp_get_theme();
    $parent = $theme->parent();
    $themeInfo = static fn ($item): array => [
        'name' => $item->get('Name'),
        'version' => $item->get('Version'),
        'stylesheet' => $item->get_stylesheet(),
        'template' => $item->get_template(),
    ];

    $plugins = [];
    foreach (get_plugins() as $file => $plugin) {
        $plugins[] = [
            'file' => $file,
            'name' => $plugin['Name'],
            'version' => $plugin['Version'],
            'active' => is_plugin_active($file),
            'network_active' => is_plugin_active_for_network($file),
        ];
    }

    $mustUse = [];
    foreach (get_mu_plugins() as $file => $plugin) {
        $mustUse[] = ['file' => $file, 'name' => $plugin['Name'], 'version' => $plugin['Version']];
    }

    $dropins = [];
    foreach (get_dropins() as $file => $plugin) {
        $dropins[] = ['file' => $file, 'name' => $plugin['Name'], 'version' => $plugin['Version']];
    }

    $postTypes = [];
    foreach (get_post_types([], 'objects') as $name => $type) {
        $metadata = [];
        foreach (get_registered_meta_keys('post', $name) as $key => $definition) {
            $metadata[] = [
                'key' => $key,
                'type' => $definition['type'] ?? null,
                'single' => $definition['single'] ?? false,
                'show_in_rest' => !empty($definition['show_in_rest']),
            ];
        }
        $postTypes[] = [
            'name' => $name,
            'label' => $type->label,
            'public' => $type->public,
            'publicly_queryable' => $type->publicly_queryable,
            'show_in_rest' => $type->show_in_rest,
            'rest_base' => $type->rest_base,
            'has_archive' => $type->has_archive,
            'rewrite_slug' => is_array($type->rewrite) ? ($type->rewrite['slug'] ?? null) : null,
            'taxonomies' => get_object_taxonomies($name),
            'supports' => array_keys(get_all_post_type_supports($name)),
            'map_meta_cap' => $type->map_meta_cap,
            'capabilities' => (array) $type->cap,
            'registered_metadata' => $metadata,
        ];
    }

    $taxonomies = [];
    foreach (get_taxonomies([], 'objects') as $name => $taxonomy) {
        $taxonomies[] = [
            'name' => $name,
            'label' => $taxonomy->label,
            'object_types' => $taxonomy->object_type,
            'hierarchical' => $taxonomy->hierarchical,
            'public' => $taxonomy->public,
            'show_in_rest' => $taxonomy->show_in_rest,
            'capabilities' => (array) $taxonomy->cap,
        ];
    }

    // Allowlist schema attributes only. Never include defaults, choices, instructions or values.
    $describeFields = static function (array $fields) use (&$describeFields): array {
        $result = [];
        foreach ($fields as $field) {
            $item = array_intersect_key($field, array_flip([
                'key', 'name', 'type', 'required', 'return_format', 'multiple',
                'post_type', 'taxonomy', 'bidirectional', 'bidirectional_target', 'clone',
            ]));
            if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $item['sub_fields'] = $describeFields($field['sub_fields']);
            }
            if (!empty($field['layouts']) && is_array($field['layouts'])) {
                $item['layouts'] = [];
                foreach ($field['layouts'] as $layout) {
                    $item['layouts'][] = [
                        'key' => $layout['key'] ?? null,
                        'name' => $layout['name'] ?? null,
                        'sub_fields' => $describeFields($layout['sub_fields'] ?? []),
                    ];
                }
            }
            $result[] = $item;
        }
        return $result;
    };

    $groups = [];
    $acfAvailable = function_exists('acf_get_field_groups') && function_exists('acf_get_fields');
    if ($acfAvailable) {
        foreach (acf_get_field_groups() as $group) {
            // Preserve OR/AND structure, but omit location values such as post or user IDs.
            $locations = [];
            foreach ($group['location'] ?? [] as $rules) {
                $locations[] = array_map(static function (array $rule): array {
                    $safe = ['param' => $rule['param'] ?? '', 'operator' => $rule['operator'] ?? ''];
                    if (in_array($safe['param'], ['post_type', 'taxonomy', 'post_format', 'user_role'], true)) {
                        $safe['value'] = $rule['value'] ?? null;
                    }
                    return $safe;
                }, $rules);
            }
            $groups[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'active' => $group['active'] ?? null,
                'show_in_rest' => $group['show_in_rest'] ?? false,
                'location' => $locations,
                'fields' => $describeFields(acf_get_fields($group) ?: []),
            ];
        }
    }

    // RegiNor-specific readiness, without exporting course text, IDs of people or option dumps.
    $coursePageId = (int) get_option('rnl_course_page_id', 0);
    $coursePage = $coursePageId ? get_post($coursePageId) : null;
    $instructorTypes = [];
    foreach ((array) apply_filters('rnl_instructor_post_types', get_option('rnl_instructor_types', [])) as $typeName) {
        if (!is_string($typeName)) { continue; }
        $type = get_post_type_object($typeName);
        $instructorTypes[] = ['name' => $typeName, 'registered' => $type !== null, 'public' => $type ? (bool) $type->public : null];
    }
    $reginorTypes = [];
    foreach (['course', 'period', 'group', 'venue', 'room', 'level'] as $kind) {
        $type = get_post_type_object('rnl_' . $kind);
        $reginorTypes[$kind] = [
            'registered' => $type !== null,
            'matches_expected_structure' => $type !== null && !$type->public && !$type->publicly_queryable
                && !$type->show_in_rest && $type->rewrite === false && $type->query_var === false
                && $type->cap->edit_posts === 'rnl_edit_' . $kind . 's'
                && isset(get_registered_meta_keys('post', 'rnl_' . $kind)['rnl_state']),
        ];
    }
    global $wpdb, $shortcode_tags;
    $report = [
        'report_version' => 2,
        'generated_at_utc' => gmdate('c'),
        'scope' => 'Structure only; no content values. Not a migration or complete security audit.',
        'environment' => [
            'wordpress' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'database' => $wpdb->db_version(),
            'type' => wp_get_environment_type(),
            'multisite' => is_multisite(),
            'locale' => get_locale(),
            'timezone' => wp_timezone_string(),
            'permalink_structure' => get_option('permalink_structure'),
            'external_object_cache' => (bool) wp_using_ext_object_cache(),
            'wp_cache' => defined('WP_CACHE') && WP_CACHE,
        ],
        'theme' => $themeInfo($theme),
        'parent_theme' => $parent ? $themeInfo($parent) : null,
        'plugins' => $plugins,
        'must_use_plugins' => $mustUse,
        'dropins' => $dropins,
        'post_types' => $postTypes,
        'taxonomies' => $taxonomies,
        'reginor' => [
            'content_types' => $reginorTypes,
            'shortcode_owned' => ($shortcode_tags['reginor_courses'] ?? null) === ['RegiNor\\Lite\\Frontend\\PublicSite', 'render'],
            'block_registered' => WP_Block_Type_Registry::get_instance()->is_registered('reginor-lite/courses'),
            'course_page' => [
                'configured' => $coursePageId > 0,
                'published_unprotected_page' => $coursePage && $coursePage->post_type === 'page' && $coursePage->post_status === 'publish' && $coursePage->post_password === '',
                'embedding_in_content' => !$coursePage ? null : (is_callable(['RegiNor\\Lite\\Frontend\\PublicSite', 'containsCourses'])
                    ? \RegiNor\Lite\Frontend\PublicSite::containsCourses($coursePage->post_content)
                    : (has_shortcode($coursePage->post_content, 'reginor_courses') || has_block('reginor-lite/courses', $coursePage->post_content))),
            ],
            'instructor_types' => $instructorTypes,
        ],
        'acf' => [
            'available' => $acfAvailable,
            'version' => defined('ACF_VERSION') ? ACF_VERSION : null,
            'field_groups' => $groups,
        ],
        'limitations' => [
            'Registered metadata only; legacy unregistered metadata requires targeted follow-up.',
            'No content samples, Avada layout conditions, CDN settings or user assignments exported.',
            'ACF location values are limited to type/taxonomy/format/role rules; review remaining rules in admin.',
            'No TEC occurrence IDs or storage tables read; determine installed version before choosing an adapter.',
            'Matching RegiNor registration shapes are not proof of ownership or absence of staging route conflicts.',
            'Embedding checks cover stored shortcode/block content; theme templates and Avada global layouts require manual inspection.',
        ],
    ];

    WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
})();
