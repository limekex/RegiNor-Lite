<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Shared presentation for RegiNor-owned TEC events only. */
final class TecDefaults
{
    public const OPTION = 'rnl_tec_defaults';
    public const TAXONOMY = 'tribe_events_cat';

    public static function read(): array
    {
        $saved = get_option(self::OPTION, []);
        $category = is_array($saved) ? absint($saved['category_id'] ?? 0) : 0;
        $image = is_array($saved) ? absint($saved['image_id'] ?? 0) : 0;
        return ['category_id' => self::categoryExists($category) ? $category : 0, 'image_id' => self::imageExists($image) ? $image : 0];
    }

    private static function categoryExists(int $id): bool
    {
        return $id > 0 && get_term($id, self::TAXONOMY) instanceof \WP_Term;
    }

    private static function imageExists(int $id): bool
    {
        return $id > 0 && get_post_status($id) !== 'trash' && wp_attachment_is_image($id) && (bool) wp_get_attachment_image_src($id, 'medium');
    }

    public static function update(array $raw): void
    {
        if (!current_user_can('manage_options')) { throw new \InvalidArgumentException(__('Du har ikke tilgang.', 'reginor-lite')); }
        if (!TecBridge::available() || !taxonomy_exists(self::TAXONOMY)) { throw new \InvalidArgumentException(__('Aktiver The Events Calendar før du velger kalenderkategori og bilde.', 'reginor-lite')); }
        $values = [];
        foreach (['category_id', 'image_id'] as $key) {
            $value = $raw[$key] ?? null;
            if ((!is_string($value) && !is_int($value)) || !ctype_digit((string) $value)) { throw new \InvalidArgumentException(__('Velg kategori og bilde fra listene på siden.', 'reginor-lite')); }
            $values[$key] = (int) $value;
        }
        if ($values['category_id'] && !self::categoryExists($values['category_id'])) { throw new \InvalidArgumentException(__('Kategorien finnes ikke i arrangementskalenderen. Velg en kategori fra listen.', 'reginor-lite')); }
        if ($values['image_id'] && !self::imageExists($values['image_id'])) { throw new \InvalidArgumentException(__('Velg et tilgjengelig bilde fra mediebiblioteket.', 'reginor-lite')); }
        update_option(self::OPTION, $values, false);
    }

    public static function apply(int $event, array $values): void
    {
        $terms = wp_set_object_terms($event, $values['category_id'] ? [$values['category_id']] : [], self::TAXONOMY, false);
        if (is_wp_error($terms)) { throw new \RuntimeException('TEC category update failed'); }
        if ($values['image_id']) { set_post_thumbnail($event, $values['image_id']); }
        else { delete_post_thumbnail($event); }
        if ((int) get_post_thumbnail_id($event) !== $values['image_id']) { throw new \RuntimeException('TEC image update failed'); }
    }
}
