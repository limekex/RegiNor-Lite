<?php
/** Run inside a disposable WordPress development environment through WP-CLI. */

// WP-CLI eval-file wraps this code before evaluating it, so strict_types cannot be declared here.

use RegiNor\Lite\Admin\ProjectPage;

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Kjør denne kontrollen med WP-CLI i det lokale testmiljøet.');
}

if (!is_plugin_active('reginor-lite/reginor-lite.php') || !class_exists(ProjectPage::class)) {
    WP_CLI::error('Pluginen er ikke aktivert og lastet.');
}

if (false === has_action('admin_menu', [ProjectPage::class, 'register'])) {
    WP_CLI::error('Administrasjonssiden er ikke registrert.');
}

$administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$administrators) {
    WP_CLI::error('Testmiljøet mangler en administrator.');
}

$originalUser = get_current_user_id();
$dieHandler = static function (): Closure {
    return static function ($message, $title, $args): void {
        throw new RuntimeException(wp_strip_all_tags($message), (int) $args['response']);
    };
};

try {
    wp_set_current_user((int) $administrators[0]);
    ob_start();
    ProjectPage::render();
    $html = ob_get_clean();

    if (!str_contains($html, 'RegiNor Lite') || !str_contains($html, 'under utvikling')) {
        throw new RuntimeException('Administrator fikk ikke forventet prosjektstatus.');
    }

    // Direct invocation must also deny an unauthenticated visitor.
    wp_set_current_user(0);
    add_filter('wp_die_handler', $dieHandler);
    $denied = false;
    try {
        ProjectPage::render();
    } catch (RuntimeException $error) {
        $denied = $error->getCode() === 403;
    }

    if (!$denied) {
        throw new RuntimeException('Uautorisert tilgang ble ikke avvist med 403.');
    }
} finally {
    remove_filter('wp_die_handler', $dieHandler);
    wp_set_current_user($originalUser);
}

WP_CLI::success('Pluginen lastes, administrator ser status og uautorisert tilgang avvises.');
