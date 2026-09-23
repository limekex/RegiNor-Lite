<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Capacity;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

/** One projection for cards, details, timetable and private demonstration. */
final class CapacityView
{
    public static function project(?array $snapshot, int $now, ?string $error = null, bool $verified = false, bool $demoPreview = false): array
    {
        $fallback = ['state' => 'unknown', 'label' => __('Sjekk ledige plasser hos LetsReg', 'reginor-lite'), 'confirmed' => false,
            'expires_at' => null, 'roles' => []];
        if (!$verified && !$demoPreview) { return $fallback; }
        if ($error !== null) { return array_replace($fallback, ['state' => 'error']); }
        if ($snapshot === null) { return $fallback; }
        try { $snapshot = Snapshot::validate($snapshot); } catch (\InvalidArgumentException) { return array_replace($fallback, ['state' => 'error']); }
        if ($snapshot['captured_at'] > $now || $snapshot['expires_at'] <= $now) { return array_replace($fallback, ['state' => 'expired']); }
        $view = array_replace($fallback, ['expires_at' => $snapshot['expires_at'], 'confirmed' => $verified]);
        if ($snapshot['roles'] !== []) {
            $labels = [];
            foreach (['leader' => __('Fører', 'reginor-lite'), 'follower' => __('Følger', 'reginor-lite')] as $key => $label) {
                $count = $snapshot['roles'][$key];
                $labels[] = $label . ': ' . ($count === null ? __('sjekk hos LetsReg', 'reginor-lite') : ($count === 0 ? __('fullt', 'reginor-lite') : __('plasser tilgjengelig', 'reginor-lite')));
            }
            return array_replace($view, ['state' => 'roles', 'label' => implode('. ', $labels), 'roles' => $labels]);
        }
        return match (true) {
            $snapshot['available'] === null => $fallback,
            $snapshot['available'] === 0 => array_replace($view, ['state' => 'full', 'label' => __('Fullt', 'reginor-lite')]),
            default => array_replace($view, ['state' => 'fresh', 'label' => __('Plasser tilgjengelig', 'reginor-lite')]),
        };
    }
}
