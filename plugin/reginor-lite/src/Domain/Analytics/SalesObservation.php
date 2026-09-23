<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Analytics;

/** Provider totals are observations, not proof of payment or campaign attribution. */
final class SalesObservation
{
    public static function fromEvent(array $event): array
    {
        $count = $event['registeredParticipants'] ?? null;
        $amount = $event['ordersTotalSum'] ?? null;
        return [
            'participants' => is_int($count) && $count >= 0 && $count <= 2147483647 ? $count : null,
            // Fixed precision for comparison only. No currency or paid/net meaning is assumed.
            'order_sum_minor' => (is_int($amount) || is_float($amount)) && is_finite((float) $amount)
                && abs($amount) <= 1000000000 && abs($amount * 100 - round($amount * 100)) < 0.00001
                ? (int) round($amount * 100) : null,
        ];
    }

    public static function change(?array $previous, array $current): array
    {
        $result = ['participants' => null, 'order_sum_minor' => null, 'increase' => false, 'gap' => null];
        if (!$previous || $current['at'] <= $previous['at']) { return $result; }
        $result['gap'] = $current['at'] - $previous['at'];
        foreach (['participants', 'order_sum_minor'] as $key) {
            if ($previous[$key] !== null && $current[$key] !== null) {
                $result[$key] = $current[$key] - $previous[$key];
                if ($result[$key] > 0) { $result['increase'] = true; }
            }
        }
        return $result;
    }

    /** Recomputed from retained consented clicks. Never persists a guessed conversion. */
    public static function coincidence(?array $previous, array $current, array $clicks, int $minutes): array
    {
        if (!in_array($minutes, [5, 15, 30, 60], true)) { throw new \InvalidArgumentException('Invalid comparison window'); }
        $delta = self::change($previous, $current);
        $result = ['reason' => 'baseline', 'journeys' => 0, 'sources' => []];
        if ($delta['gap'] === null) { return $result; }
        if (!$delta['increase']) { $result['reason'] = 'no_increase'; return $result; }
        if ($delta['gap'] > 1200) { $result['reason'] = 'gap'; return $result; }
        $seen = []; $sources = [];
        foreach ($clicks as $click) {
            if ($click['at'] < $previous['at'] - $minutes * 60 || $click['at'] > $current['at']) { continue; }
            $seen[$click['journey']] = true;
            $key = json_encode([$click['source'], $click['medium'], $click['campaign']], JSON_THROW_ON_ERROR);
            $sources[$key] = ['source' => $click['source'], 'medium' => $click['medium'], 'campaign' => $click['campaign']];
        }
        $result['journeys'] = count($seen); $result['sources'] = array_values($sources);
        $result['reason'] = !$seen ? 'no_clicks' : (count($seen) === 1 ? 'one_candidate' : 'multiple_candidates');
        return $result;
    }
}
