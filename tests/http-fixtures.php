<?php
/** Setup/cleanup only synthetic fixtures for the local HTTP workflow test. */
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt testmiljø.'); }
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
wp_set_current_user($admin);
if (($args[0] ?? '') === 'cleanup') {
    $data = json_decode(base64_decode($args[1], true), true, 32, JSON_THROW_ON_ERROR);
    foreach (get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish', 'trash'], 'posts_per_page' => -1, 'fields' => 'ids']) as $id) {
        $state = get_post_meta($id, ContentTypes::META, true);
        if (in_array($state['data']['period_id'] ?? 0, $data['created'], true)) { wp_delete_post($id, true); }
    }
    foreach (array_reverse($data['created']) as $id) { wp_delete_post($id, true); }
    require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($data['user']);
    if ($data['types_existed']) { update_option('rnl_instructor_types', $data['old_types']); }
    else { delete_option('rnl_instructor_types'); }
    if (array_key_exists('old_page', $data)) { if ($data['old_page'] === null) { delete_option('rnl_course_page_id'); } else { update_option('rnl_course_page_id', $data['old_page']); } }
    if (empty($data['capacity_cron_existed']) && !get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish'], 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => \RegiNor\Lite\Infrastructure\CapacityStore::META])) {
        wp_clear_scheduled_hook(\RegiNor\Lite\Infrastructure\CapacityStore::HOOK);
    }
    WP_CLI::success('HTTP-testobjekter er ryddet bort.');
    return;
}
if (($args[0] ?? '') === 'capacity-scenario') {
    $id = (int) ($args[1] ?? 0); $scenario = $args[2] ?? 'fresh';
    $group = get_post_meta($id, ContentTypes::META, true);
    if (get_post_type($id) !== 'rnl_group' || !in_array(get_post_field('post_title', $group['data']['period_id'] ?? 0), ['HTTP periode', 'HTTP offentlig periode'], true)) { throw new RuntimeException('Bare syntetiske HTTP-testkurs kan endres.'); }
    $store = new \RegiNor\Lite\Infrastructure\CapacityStore(); $old = $store->inspect($id);
    $store->configure($id, $old['version'] ?? 0, $scenario); $store->runDue($id);
    $state = $store->inspect($id);
    if ($scenario === 'fresh') {
        // Let the HTTP test exercise a manual request without waiting a real minute.
        $state['requested_at'] = time() - 61; $state['not_before'] = time() - 1;
        update_post_meta($id, \RegiNor\Lite\Infrastructure\CapacityStore::META, wp_slash($state));
    }
    WP_CLI::success('Syntetisk kapasitetsprøve klar.'); return;
}
if (($args[0] ?? '') === 'public-state') {
    $id = (int) ($args[1] ?? 0); $mode = $args[2] ?? '';
    if (get_post_type($id) !== 'rnl_period' || get_post_field('post_title', $id) !== 'HTTP offentlig periode') { throw new RuntimeException('Bare syntetisk testperiode kan endres.'); }
    $state = get_post_meta($id, ContentTypes::META, true);
    if ($mode === 'expired') { $state['data']['visible_until'] = gmdate('Y-m-d\TH:i:s\Z', time() - 1); }
    elseif ($mode === 'future') { $state['data']['visible_from'] = gmdate('Y-m-d\TH:i:s\Z', time() + 3600); }
    elseif ($mode === 'closed') { $state['data']['sales_until'] = gmdate('Y-m-d\TH:i:s\Z', time() - 1); }
    elseif ($mode === 'cancelled') { $state['data']['cancelled'] = true; }
    elseif ($mode === 'open') { $state['data']['visible_from'] = gmdate('Y-m-d\TH:i:s\Z', time() - 86400); $state['data']['visible_until'] = '2031-01-01T00:00:00Z'; $state['data']['sales_until'] = '2029-12-31T00:00:00Z'; $state['data']['cancelled'] = false; }
    else { throw new RuntimeException('Ugyldig testmodus.'); }
    update_post_meta($id, ContentTypes::META, wp_slash($state));
    WP_CLI::success('Syntetisk status oppdatert.'); return;
}
$repo = new CourseRepository(); $created = [];
$venue = $created[] = $repo->create('venue', ['title' => 'HTTP teststed', 'address' => 'Testgate 7']);
$room = $created[] = $repo->create('room', ['title' => 'HTTP testsal', 'venue_id' => $venue]);
$course = $created[] = $repo->create('course', ['title' => 'HTTP testkurs', 'description' => 'Kurs kun for automatisk test.', 'level_description' => 'Ingen forkunnskaper', 'dance_style' => 'Salsa', 'partner_info' => 'Valgfri partner']);
$instructor = $created[] = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'HTTP testinstruktør']);
$old = get_option('rnl_instructor_types', null); update_option('rnl_instructor_types', ['post']);
$login = 'rnl-http-' . wp_generate_uuid4(); $password = wp_generate_password(24, false);
$user = wp_insert_user(['user_login' => $login, 'user_pass' => $password, 'role' => 'rnl_course_manager']);
if (is_wp_error($user)) { throw new RuntimeException('Kunne ikke opprette testbruker.'); }
$extra = ['capacity_cron_existed' => wp_next_scheduled(\RegiNor\Lite\Infrastructure\CapacityStore::HOOK) !== false];
if (($args[0] ?? '') === 'public') {
    $extra['old_page'] = get_option('rnl_course_page_id', null);
    $page = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'HTTP kursoversikt', 'post_content' => '[reginor_courses]']);
    $embed = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'HTTP innbygging', 'post_content' => '[reginor_courses]']);
    $synced = $created[] = wp_insert_post(['post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => 'HTTP synkronisert kursblokk', 'post_content' => '<!-- wp:reginor-lite/courses /-->']);
    $blockPage = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'HTTP blokkinnbygging', 'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:block {"ref":' . $synced . '} /--></div><!-- /wp:group -->']);
    update_option('rnl_course_page_id', $page);
    $level = $created[] = $repo->create('level', ['title' => 'HTTP nivå Nybegynner', 'description' => 'Ingen forkunnskaper.', 'sort_order' => 10, 'active' => true]);
    $p = ['title' => 'HTTP offentlig periode', 'timezone' => 'Europe/Oslo', 'start_date' => '2030-01-07', 'default_session_count' => 2,
        'default_room_id' => $room, 'default_price_minor' => 123450, 'default_price_basis' => 'person',
        'visible_from' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400), 'visible_until' => '2031-01-01T00:00:00Z',
        'sales_from' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400), 'sales_until' => '2029-12-31T00:00:00Z', 'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []];
    $period = $created[] = $repo->create('period', $p);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'level_id' => $level, 'instructor_ids' => [$instructor], 'registration_status' => 'available', 'registration_url' => 'https://www.letsreg.com/event/http-salsa']);
    $repo->confirm($repo->previewGroup($group, 1, []));
    $repo->publish($repo->previewPublication($period, 1, true));
    $extra += ['page' => $page, 'period' => $period, 'group' => $group, 'level' => $level, 'url' => get_permalink($page), 'course_url' => \RegiNor\Lite\Frontend\PublicSite::url($group), 'period_url' => \RegiNor\Lite\Frontend\PublicSite::url(null, ['rnl_period' => $period]), 'embed_urls' => [get_permalink($embed), get_permalink($blockPage)]];
}
WP_CLI::line(wp_json_encode(['created' => $created, 'user' => $user, 'login' => $login, 'password' => $password, 'room' => $room,
    'course' => $course, 'instructor' => $instructor, 'old_types' => $old, 'types_existed' => $old !== null] + $extra));
