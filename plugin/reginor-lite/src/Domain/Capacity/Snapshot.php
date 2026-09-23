<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Capacity;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;

/** A complete, normalized observation. No provider payload or participant data. */
final class Snapshot
{
    public static function accept(array $data, ?array $previous, int $now): array
    {
        $data = self::validate($data);
        if ($data['captured_at'] > $now || ($previous !== null && (
            $data['captured_at'] < $previous['captured_at']
            || ($previous['source_version'] !== null && ($data['source_version'] === null || $data['source_version'] < $previous['source_version']))))) {
            throw new InvalidArgumentException(__('Kontrollen ga et eldre eller ugyldig kapasitetsbilde.', 'reginor-lite'));
        }
        return $data;
    }

    public static function validate(array $data): array
    {
        $keys = ['complete', 'captured_at', 'expires_at', 'available', 'roles', 'source_version'];
        if (array_diff(array_keys($data), $keys) || array_diff($keys, array_keys($data)) || $data['complete'] !== true
            || !is_int($data['captured_at']) || $data['captured_at'] < 1 || !is_int($data['expires_at'])
            || $data['expires_at'] <= $data['captured_at'] || $data['expires_at'] > $data['captured_at'] + 900
            || ($data['source_version'] !== null && (!is_int($data['source_version']) || $data['source_version'] < 0))) {
            throw new InvalidArgumentException(__('Kapasitetsbildet er ufullstendig.', 'reginor-lite'));
        }
        self::count($data['available']);
        if (!is_array($data['roles']) || ($data['roles'] !== [] && (
            count($data['roles']) !== 2 || !array_key_exists('leader', $data['roles']) || !array_key_exists('follower', $data['roles'])))) {
            throw new InvalidArgumentException(__('Begge danserollene må være kontrollert.', 'reginor-lite'));
        }
        foreach ($data['roles'] as $count) { self::count($count); }
        // A total and role counts need a verified pool model. This demo contract keeps them separate.
        if ($data['roles'] !== [] && $data['available'] !== null) {
            throw new InvalidArgumentException(__('Samlet kapasitet kan ikke brukes som kapasitet for hver rolle.', 'reginor-lite'));
        }
        return $data;
    }

    private static function count(mixed $value): void
    {
        if ($value !== null && (!is_int($value) || $value < 0 || $value > 1000000)) {
            throw new InvalidArgumentException(__('Antall plasser er ukjent eller ugyldig.', 'reginor-lite'));
        }
    }
}
