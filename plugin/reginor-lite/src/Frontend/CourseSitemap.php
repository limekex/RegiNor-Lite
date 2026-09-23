<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\WebIdentity;

final class CourseSitemap extends \WP_Sitemaps_Provider
{
    public function __construct() { $this->name = 'reginor'; $this->object_type = 'course'; }
    private function urls(): array
    {
        PublicSite::noCache(); if (!PublicSite::pageId()) { return []; }
        $catalog = (new Catalog())->read(); $urls = [];
        $languages = PublicRoutes::enabled() ? array_keys(WebIdentity::languages()) : [];
        foreach ($catalog['periods'] as $p) {
            $urls[] = PublicSite::url(null, ['rnl_period' => $p['id']]);
            foreach ($languages as $code) { $urls[] = PublicRoutes::url($p['id'], null, $code); }
        }
        foreach ($catalog['groups'] as $g) {
            $urls[] = PublicSite::url($g['id']);
            foreach ($languages as $code) { $urls[] = PublicRoutes::url($g['period_id'], $g['id'], $code); }
        }
        return array_values(array_unique($urls));
    }
    public function get_url_list($page_num, $object_subtype = ''): array
    {
        return array_map(static fn ($url) => ['loc' => $url], array_slice($this->urls(), (max(1, (int) $page_num) - 1) * 200, 200));
    }
    public function get_max_num_pages($object_subtype = ''): int { return (int) ceil(count($this->urls()) / 200); }
}
