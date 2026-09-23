<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Short-lived checked snapshots allow several events to be reviewed without a shared last-event cache. */
final class LetsRegImportSource
{
    public static function issue(): string
    {
        LetsRegMapping::authorizeImport();
        $source = LetsRegMapping::source();
        if (!$source) { throw new \RuntimeException(__('Hent arrangementet på nytt.', 'reginor-lite'), 409); }
        $receipt = ['source' => $source, 'actor' => get_current_user_id(), 'context' => LetsRegConnection::importContext(),
            'expires' => $source['checked_at'] + 900];
        $json = wp_json_encode($receipt, JSON_THROW_ON_ERROR);
        return base64_encode($json) . '.' . hash_hmac('sha256', $json, wp_salt('auth'));
    }

    public static function read(string $receipt): array
    {
        LetsRegMapping::authorizeImport();
        $parts = explode('.', $receipt);
        $json = count($parts) === 2 && strlen($receipt) <= 300000 ? base64_decode($parts[0], true) : false;
        if ($json === false || !hash_equals(hash_hmac('sha256', $json, wp_salt('auth')), $parts[1] ?? '')) {
            throw new \RuntimeException(__('Importgrunnlaget er ugyldig. Velg arrangementet på nytt.', 'reginor-lite'), 403);
        }
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if ($data['actor'] !== get_current_user_id()) { throw new \RuntimeException(__('Importgrunnlaget tilhører en annen bruker.', 'reginor-lite'), 403); }
        if ($data['expires'] <= time() || !hash_equals($data['context'], LetsRegConnection::importContext())) {
            throw new \RuntimeException(__('Importgrunnlaget er utløpt eller tilgangen er endret. Velg arrangementet på nytt; kursfeltene dine er beholdt.', 'reginor-lite'), 409);
        }
        return $data['source'];
    }

    public static function assertMapping(string $receipt, array $mapping): void
    {
        $source = self::read($receipt); $choices = [];
        foreach ($mapping['categories'] as $category) { $choices[$category['id']] = array_intersect_key($category, array_flip(['role', 'registration'])); }
        if (LetsRegMapping::fromSource($source, $choices) !== $mapping) {
            throw new \RuntimeException(__('Koblingen stemmer ikke med importgrunnlaget.', 'reginor-lite'), 409);
        }
    }
}
