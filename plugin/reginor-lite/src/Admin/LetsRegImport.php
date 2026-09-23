<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\LetsRegMapping;
use RegiNor\Lite\Infrastructure\LetsRegImportSource;
use function RegiNor\Lite\translate as __;

/** Entered only after the shared AJAX controller's nonce, import permission and period checks. */
final class LetsRegImport
{
    public static function dispatch(array $input, CourseRepository $repo, array $period): array
    {
        LetsRegMapping::authorizeImport();
        $id = CourseActions::integer($input['id'] ?? '');
        if ($input['operation'] === 'confirm_import_batch') {
            $encoded = $input['proposals'] ?? null;
            if (!is_array($encoded) || !array_is_list($encoded) || count($encoded) > 20) { throw new \InvalidArgumentException(__('Velg opptil 20 kurs per import.', 'reginor-lite')); }
            $proposals = array_map(static fn ($value) => CourseActions::decode(is_string($value) ? $value : ''), $encoded);
            $ids = $repo->confirmImportBatch($id, $proposals);
            return ['count' => count($ids), 'redirect' => add_query_arg(['page' => 'reginor-lite', 'period' => $id, 'step' => 'courses', 'updated' => 1], admin_url('admin.php'))];
        }
        if ($input['operation'] === 'confirm_import') {
            $proposal = CourseActions::decode(CourseActions::scalar($input, 'proposal'));
            if (($proposal['data']['period_id'] ?? 0) !== $id) { throw new \RuntimeException(__('Forhåndsvisningen tilhører en annen periode.', 'reginor-lite'), 403); }
            $group = $repo->confirmImport($proposal);
            return ['redirect' => add_query_arg(['page' => 'reginor-lite', 'period' => $id, 'group' => $group, 'step' => 'courses', 'updated' => 1], admin_url('admin.php'))];
        }
        if (!is_array($input['data'] ?? null) || !is_array($input['data']['categories'] ?? null)) { throw new \InvalidArgumentException(__('Velg et arrangement og minst én priskategori.', 'reginor-lite')); }
        $receipt = isset($input['receipt']) ? CourseActions::scalar($input, 'receipt') : '';
        $source = $receipt !== '' ? LetsRegImportSource::read($receipt) : null;
        $mapping = $source ? LetsRegMapping::fromSource($source, $input['data']['categories'])
            : LetsRegMapping::build(CourseActions::integer($input['event_id'] ?? ''), CourseActions::scalar($input, 'verification_id'), $input['data']['categories']);
        $newCourse = null;
        if (($input['description_mode'] ?? '') === 'new') {
            if (!is_array($input['new_course'] ?? null)) { throw new \InvalidArgumentException(__('Fyll ut den nye kursbeskrivelsen.', 'reginor-lite')); }
            $newCourse = [];
            foreach (['title', 'description', 'dance_style', 'level_description', 'partner_info'] as $key) { $newCourse[$key] = CourseActions::scalar($input['new_course'], $key); }
        }
        $defaults = $repo->newGroupDefaults($id);
        $defaults['course_id'] = $newCourse !== null ? 0 : CourseActions::integer($input['course_id'] ?? '');
        $raw = $input['data']; unset($raw['categories']);
        $raw['start_date'] = $period['data']['start_date'];
        $parsed = CourseActions::parse('group', $raw, $defaults, $newCourse !== null);
        foreach (['period_id', 'period_version', 'course_id', 'sessions', 'letsreg_mapping'] as $key) { unset($parsed[$key]); }
        $proposal = $repo->previewImport($id, CourseActions::integer($input['version'] ?? ''), $defaults['course_id'], $parsed, $mapping, $newCourse, $receipt, (isset($input['description_choice']) ? CourseActions::scalar($input, 'description_choice') : 'auto'));
        return ['proposal' => base64_encode(wp_json_encode($proposal, JSON_THROW_ON_ERROR)), 'html' => CoursePage::importPreview($proposal)];
    }
}
