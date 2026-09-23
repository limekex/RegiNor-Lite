<?php
/** Temporary accounts only, used to exercise real WordPress admin routing. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
if (($args[0] ?? '') === 'appearance-snapshot') { WP_CLI::line(wp_json_encode(['value' => get_option('rnl_appearance', null), 'calendar' => get_option('rnl_tec_defaults', null)])); return; }
if (($args[0] ?? '') === 'appearance-restore') {
    $data = json_decode(base64_decode($args[1], true), true, 32, JSON_THROW_ON_ERROR);
    if ($data['value'] === null) { delete_option('rnl_appearance'); } else { update_option('rnl_appearance', $data['value']); }
    if ($data['calendar'] === null) { delete_option('rnl_tec_defaults'); } else { update_option('rnl_tec_defaults', $data['calendar']); }
    \RegiNor\Lite\Infrastructure\TecBridge::sync(); return;
}
if (($args[0] ?? '') === 'cleanup') {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach (json_decode(base64_decode($args[1], true), true, 16, JSON_THROW_ON_ERROR) as $account) {
        $user = get_userdata($account['id']);
        if ($user && str_starts_with($user->user_login, 'rnl-menu-test-') && $user->user_login === $account['login']) { wp_delete_user($user->ID); }
    }
    return;
}
$accounts = [];
foreach (['administrator', 'rnl_course_manager'] as $role) {
    $login = 'rnl-menu-test-' . wp_generate_uuid4(); $password = wp_generate_password(24, false);
    $id = wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'role' => $role]);
    if (is_wp_error($id)) { throw new RuntimeException('Kunne ikke opprette lokal menytestbruker.'); }
    $accounts[$role] = ['id' => $id, 'login' => $login, 'password' => $password];
}
WP_CLI::line(wp_json_encode($accounts));
