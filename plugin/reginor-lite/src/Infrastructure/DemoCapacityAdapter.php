<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

/** Synthetic observations only. No networking, secrets, external IDs or live capability. */
final class DemoCapacityAdapter
{
    public static function scenarios(): array { return ['unknown' => __('Ukjent kapasitet', 'reginor-lite'), 'fresh' => __('Ledige plasser', 'reginor-lite'), 'full' => __('Fullt kurs', 'reginor-lite'),
        'roles' => __('Plass for førere, fullt for følgere', 'reginor-lite'), 'expired' => __('Utdaterte opplysninger', 'reginor-lite'), 'error' => __('Kontrollen feiler', 'reginor-lite'),
        'rate_limit' => __('Mange forespørsler – prøv igjen senere', 'reginor-lite')]; }

    public function fetch(string $scenario, int $now): array
    {
        if (!isset(self::scenarios()[$scenario])) { throw new \InvalidArgumentException(__('Velg en av de viste prøvesituasjonene.', 'reginor-lite')); }
        if (in_array($scenario, ['error', 'rate_limit'], true)) {
            return ['error' => $scenario === 'error' ? 'temporary' : 'rate_limit', 'retry_after' => $scenario === 'rate_limit' ? 600 : null];
        }
        $captured = $scenario === 'expired' ? $now - 901 : $now;
        return ['snapshot' => ['complete' => true, 'captured_at' => $captured, 'expires_at' => $captured + 900,
            'available' => match ($scenario) { 'fresh', 'expired' => 8, 'full' => 0, default => null },
            'roles' => $scenario === 'roles' ? ['leader' => 3, 'follower' => 0] : [], 'source_version' => null]];
    }
}
