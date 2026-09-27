<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use function RegiNor\Lite\translate as __;

/** Embeds only public courses with a remaining session allocated to this instructor. */
final class InstructorCourses
{
    private static int $instances = 0;

    public function __construct(private readonly Catalog $catalog = new Catalog()) {}

    public static function render(array|string $attributes = []): string
    {
        return (new self())->output($attributes);
    }

    public function output(array|string $attributes = []): string
    {
        PublicSite::noCache();
        $attributes = is_array($attributes) ? $attributes : [];
        $value = $attributes['instructor_id'] ?? get_queried_object_id();
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)) { return ''; }
        $id = (int) $value;
        $type = get_post_type($id);
        if (!in_array($type, (array) apply_filters('rnl_instructor_post_types', get_option('rnl_instructor_types', [])), true)
            || get_post_status($id) !== 'publish' || get_post_field('post_password', $id) !== '') { return ''; }
        $catalog = $this->catalog->read();
        foreach ($catalog['groups'] as $groupId => &$group) {
            $times = [];
            foreach ($group['sessions'] as $session) {
                if ($session['status'] === 'cancelled' || strtotime($session['ends_at']) <= $catalog['now']) { continue; }
                foreach ($session['instructor_profiles'] as $profile) {
                    if ($profile['id'] === $id || $profile['display_id'] === $id
                        || (int) apply_filters('wpml_object_id', $id, $type, true) === $profile['display_id']) {
                        $times[] = wp_date('l', strtotime($session['starts_at']), new \DateTimeZone($session['timezone'])) . ' · ' . $session['start_time'] . '–' . $session['end_time'];
                        break;
                    }
                }
            }
            if (!$times) { unset($catalog['groups'][$groupId]); continue; }
            $group['teaching_times'] = implode(', ', array_unique($times));
        }
        unset($group);
        if (!$catalog['groups']) { return '<div class="rnl-ui"><p>' . esc_html(__('Ingen kommende kurs er lagt ut for denne instruktøren ennå.', 'reginor-lite')) . '</p></div>'; }
        $periodIds = array_unique(array_column($catalog['groups'], 'period_id'));
        usort($periodIds, static fn ($a, $b) => strcmp($catalog['periods'][$a]['first'] ?? '', $catalog['periods'][$b]['first'] ?? ''));
        $html = '';
        $instance = ++self::$instances;
        foreach ($periodIds as $periodId) {
            $html .= '<div class="rnl-instructor-courses"><h2>' . esc_html($catalog['periods'][$periodId]['title']) . '</h2>';
            $html .= (new Renderer())->render($catalog, ['rnl_period' => $periodId], 'list', ['list'], [
                'show_header' => false, 'show_filters' => false, 'show_view_switch' => false,
                'instance' => 'instructor-' . $instance . '-' . $periodId,
                'base_url' => is_singular() ? get_permalink(get_queried_object_id()) : PublicSite::url(),
            ]) . '</div>';
        }
        return $html;
    }
}
