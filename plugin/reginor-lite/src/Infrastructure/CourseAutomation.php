<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Opt-in policy; no provider requests or mail while holding the course transaction. */
final class CourseAutomation
{
    public const META = '_rnl_source_automation';
    public const NOTICE = '_rnl_source_notice';

    public static function boot(): void
    {
        add_action(LetsRegAvailabilityStore::HOOK, [self::class, 'run'], 20);
    }

    public static function settings(int $id): array
    {
        $value = get_post_meta($id, self::META, true);
        return (is_array($value) ? $value : []) + ['time' => false, 'price' => false, 'email' => false, 'category' => 0, 'actor' => 0, 'key' => '', 'seen' => [], 'local' => [], 'status' => '', 'at' => 0];
    }

    public static function save(int $id, int $version, array $options): void
    {
        LetsRegMapping::authorize();
        Mutation::run(static function () use ($id, $version, $options): void {
            $repo = new CourseRepository(); $state = $repo->get($id, 'group');
            if ($state['version'] !== $version) { throw new VersionConflict(); }
            $data = $state['data']; $repo->get($data['period_id'], 'period');
            $review = LetsRegChanges::inspect($id, $data);
            if (!$data['letsreg_mapping'] || ((!empty($options['time']) || !empty($options['price']) || !empty($options['email'])) && empty($review['fresh']))) { throw new \RuntimeException(__('Kontroller LetsReg nå før du lagrer automatikk og varsler.', 'reginor-lite'), 409); }
            $config = ['time' => !empty($options['time']), 'price' => !empty($options['price']), 'email' => !empty($options['email']),
                'category' => (int) ($options['category'] ?? 0), 'actor' => get_current_user_id(),
                'key' => LetsRegChanges::key($data['letsreg_mapping']), 'seen' => $review['latest']['snapshot'] ?? [],
                'local' => array_intersect_key($data, array_flip(['start_time', 'end_time', 'timezone', 'price_minor', 'price_basis', 'currency'])),
                'status' => '', 'at' => time()];
            if ($config['price'] && !in_array($config['category'], array_column($data['letsreg_mapping']['categories'], 'id'), true)) {
                throw new \InvalidArgumentException(__('Velg den koblede priskategorien som skal styre kursets viste pris.', 'reginor-lite'));
            }
            if (($config['time'] || $config['price'] || $config['email']) && $review['previous'] === null) { LetsRegChanges::baseline($id, $data['letsreg_mapping'], $config['seen']); }
            $repo->saveAutomationSettings($id, $version, $config);
        }, true);
    }

    public static function put(int $id, string $key, array $data): void
    {
        Mutation::touch($id);
        if (!update_post_meta($id, $key, wp_slash($data)) && get_post_meta($id, $key, true) !== $data) {
            throw new \RuntimeException(__('Oppfølgingen kunne ikke lagres. Prøv igjen.', 'reginor-lite'), 503);
        }
    }

    /** WordPress cron runs as anonymous; delegated authority is rechecked for every course. */
    public static function run(): void
    {
        if (Mutation::active()) { return; }
        foreach (get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish'], 'posts_per_page' => -1,
            'fields' => 'ids', 'meta_key' => self::META, 'suppress_filters' => true]) as $id) {
            try { self::process((int) $id); } catch (\Throwable) { /* Retry on next normal cron; never interrupt course editing. */ }
        }
    }

    public static function process(int $id): void
    {
        if (Mutation::active()) { return; }
        $config = self::settings($id);
        if (!$config['time'] && !$config['price'] && !$config['email']) { return; }
        $original = get_current_user_id();
        try {
            wp_set_current_user((int) $config['actor']);
            if (!LetsRegMapping::canUse() || !current_user_can('edit_post', $id)) { return; }
            $repo = new CourseRepository(); $state = $repo->get($id, 'group');
            $repo->get($state['data']['period_id'], 'period');
            if (!$state['data']['letsreg_mapping'] || LetsRegChanges::key($state['data']['letsreg_mapping']) !== $config['key']) { return; }
            $review = LetsRegChanges::inspect($id, $state['data']);
            if (empty($review['fresh']) || $review['previous'] === null) { return; }
            $source = $review['latest']['snapshot']; $applied = [];
            try { $applied = $repo->applySourceAutomation($id, $state['version'], LetsRegChanges::hash($source)); }
            catch (\RuntimeException | \InvalidArgumentException $error) {
                Mutation::run(static function () use ($id, $error): void {
                    $current = self::settings($id); $current['status'] = $error->getMessage(); $current['at'] = time(); self::put($id, self::META, $current);
                }, true);
            }
            $state = $repo->get($id, 'group'); $review = LetsRegChanges::inspect($id, $state['data']);
            $pending = array_diff(array_keys($review['changes']), ['lastUpdate']);
            $config = self::settings($id);
            if ($config['email'] && ($pending || $applied || $config['status'])) {
                self::queueCurrent($id, $state['data'], $applied);
            }
            self::deliver($id);
        } finally { wp_set_current_user($original); }
    }

    /** Persist the outbox with course changes, then deliver after commit from cron. */
    public static function queueCurrent(int $id, array $data, array $applied = []): void
    {
        $config = self::settings($id);
        if (!$config['email']) { return; }
        $review = LetsRegChanges::inspect($id, $data);
        if (empty($review['latest'])) { return; }
        $signature = $review['latest']['snapshot']; unset($signature['lastUpdate']);
        $pending = array_values(array_diff(array_keys($review['changes']), ['lastUpdate']));
        $base = hash('sha256', wp_json_encode([$signature, $pending, $config['status']]));
        $existing = get_post_meta($id, self::NOTICE, true);
        if (!$applied && is_array($existing) && ($existing['base'] ?? '') === $base) { return; }
        self::enqueue($id, ['base' => $base, 'hash' => hash('sha256', wp_json_encode([$base, $applied])),
            'title' => $data['title'], 'period' => $data['period_id'], 'applied' => $applied,
            'pending' => $pending, 'reason' => $config['status'], 'sent' => [], 'retry_at' => 0]);
    }

    public static function enqueue(int $id, array $notice): void
    {
        Mutation::run(static function () use ($id, $notice): void {
            $previous = get_post_meta($id, self::NOTICE, true);
            if (is_array($previous) && ($previous['hash'] ?? '') === $notice['hash']) { return; }
            self::put($id, self::NOTICE, $notice);
        }, true);
    }

    private static function deliveryProgress(int $id, array $notice): void
    {
        Mutation::run(static function () use ($id, $notice): void {
            $current = get_post_meta($id, self::NOTICE, true);
            if (is_array($current) && ($current['hash'] ?? '') === $notice['hash']) { self::put($id, self::NOTICE, $notice); }
        }, true);
    }

    public static function deliver(int $id): void
    {
        $name = 'rnl:mail:' . md5(DatabaseLock::name() . ':' . $id);
        if (!DatabaseLock::acquire($name, 0)) { return; }
        try {
            $config = self::settings($id); $notice = get_post_meta($id, self::NOTICE, true);
            if (!$config['email'] || !is_array($notice) || ($notice['retry_at'] ?? 0) > time()) { return; }
            $url = add_query_arg(['page' => 'reginor-lite', 'period' => $notice['period'], 'group' => $id], admin_url('admin.php')) . '#rnl-source-review';
            $body = sprintf(__('Kurs: %s', 'reginor-lite'), $notice['title']) . "\n\n";
            if ($notice['applied']) { $body .= __('Utførte endringer:', 'reginor-lite') . "\n" . implode("\n", $notice['applied']) . "\n\n"; }
            if ($notice['pending']) { $body .= __('Endringer venter på manuell gjennomgang.', 'reginor-lite') . "\n\n"; }
            if ($notice['reason']) { $body .= $notice['reason'] . "\n\n"; }
            $body .= __('Se detaljer og godkjenn tekst i RegiNor:', 'reginor-lite') . "\n" . $url;
            foreach (get_users(['blog_id' => get_current_blog_id(), 'role' => 'rnl_course_manager']) as $user) {
                if (!user_can($user, 'edit_post', $id) || in_array($user->ID, $notice['sent'], true) || !is_email($user->user_email)) { continue; }
                $ok = wp_mail($user->user_email, sprintf(__('[RegiNor] LetsReg-endringer: %s', 'reginor-lite'), sanitize_text_field($notice['title'])), $body);
                if ($ok) { $notice['sent'][] = $user->ID; self::deliveryProgress($id, $notice); }
            }
            $notice['retry_at'] = time() + HOUR_IN_SECONDS;
            self::deliveryProgress($id, $notice);
        } finally { DatabaseLock::release($name); }
    }
}
