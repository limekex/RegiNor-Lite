<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Domain\Capacity\Retry;
use function RegiNor\Lite\translate as __;

/** Bounded admin checks, delegated course lookups and polling; tokens never enter persistent state. */
final class LetsRegConnection
{
    private static bool $pollingBatch = false;
    private static ?array $batchToken = null;
    public const OPTION = 'rnl_letsreg_connection';
    public const ORIGIN = 'https://integrate.deltager.no';
    public const TOKEN_URL = self::ORIGIN . '/swagger/token';

    private static function setting(string $name): string
    {
        $value = defined($name) ? constant($name) : getenv($name);
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    private static function config(): ?array
    {
        $c = [];
        foreach (['affiliate_id', 'organizer_id', 'username', 'password', 'client_id'] as $key) { $c[$key] = self::setting('RNL_LETSREG_' . strtoupper($key)); }
        foreach (['affiliate_id', 'organizer_id'] as $key) {
            $id = filter_var($c[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($id === false) { return null; } $c[$key] = $id;
        }
        if ($c['username'] === '' || strlen($c['username']) > 254 || preg_match('/[\x00-\x20\x7f:]/', $c['username'])
            || $c['password'] === '' || strlen($c['password']) > 4096 || str_contains($c['password'], "\0")
            || strlen($c['client_id']) > 200 || preg_match('/[\x00-\x1f\x7f]/', $c['client_id'])) { return null; }
        return $c;
    }

    private static function fingerprint(#[\SensitiveParameter] array $config): string
    {
        return hash_hmac('sha256', serialize($config), wp_salt('auth'));
    }

    private static function authorize(): void
    {
        if (!current_user_can('manage_options')) { throw new \RuntimeException(__('Du har ikke tilgang.', 'reginor-lite'), 403); }
    }

    public static function inspect(): array
    {
        self::authorize();
        return self::state();
    }

    /** Course linking uses only the current user's last checked event, never another user's selection. */
    public static function inspectCourse(): array
    {
        LetsRegMapping::authorize();
        $state = self::state();
        if (($state['checked_by'] ?? 0) !== get_current_user_id()) {
            unset($state['event'], $state['search'], $state['verification_id']);
        }
        return $state;
    }

    private static function state(): array
    {
        $config = self::config();
        if (!$config) { return ['configured' => false, 'state' => 'missing', 'next_attempt_at' => 0]; }
        $state = get_option(self::OPTION, []);
        if (!is_array($state) || !hash_equals(self::fingerprint($config), (string) ($state['fingerprint'] ?? ''))) {
            $state = ['state' => 'unchecked', 'next_attempt_at' => 0];
        }
        unset($state['fingerprint']);
        if (($state['state'] ?? '') === 'verified' && (int) ($state['verified_until'] ?? 0) <= time()) { $state['state'] = 'expired'; }
        return $state + ['configured' => true, 'organizer_id' => $config['organizer_id'], 'affiliate_id' => $config['affiliate_id']];
    }

    private static function save(array $state): void
    {
        if (!update_option(self::OPTION, $state, false) && get_option(self::OPTION) !== $state) {
            throw new \RuntimeException(__('Tilkoblingskontrollen kunne ikke lagres. Prøv igjen.', 'reginor-lite'), 503);
        }
    }

    /** Opaque binding for short-lived import receipts; never exposes credentials or tokens. */
    public static function importContext(): string
    {
        LetsRegMapping::authorizeImport();
        if (self::state()['state'] !== 'verified') {
            throw new \RuntimeException(__('LetsReg-kontrollen er utløpt eller tilgangen er endret. Hent arrangementene på nytt; kursfeltene dine er beholdt.', 'reginor-lite'), 409);
        }
        return self::fingerprint(self::config());
    }

    public static function check(?int $eventId = null, ?array $search = null, bool $interactive = false): array
    {
        self::authorize();
        return self::performCheck($eventId, $search, $interactive);
    }

    /** Read-only provider operations with a fixed, server-configured organizer; no general API proxy. */
    public static function checkCourse(?int $eventId = null, ?array $search = null): array
    {
        LetsRegMapping::authorize();
        if (($eventId === null) === ($search === null)) {
            throw new \InvalidArgumentException(__('Velg et arrangement eller skriv et søkeord.', 'reginor-lite'));
        }
        if (!self::config()) {
            throw new \RuntimeException(__('Administrator må sette opp LetsReg-tilkoblingen før du kan søke. Kursendringene dine er beholdt.', 'reginor-lite'), 400);
        }
        return self::performCheck($eventId, $search, true);
    }

    private static function performCheck(?int $eventId, ?array $search, bool $interactive): array
    {
        if ($search !== null && ($eventId !== null || array_diff(array_keys($search), ['query', 'offset'])
            || !is_string($search['query'] ?? null) || strlen($search['query']) > 120 || preg_match('/[\x00-\x1f\x7f]/', $search['query'])
            || !is_int($search['offset'] ?? null) || $search['offset'] < 0 || $search['offset'] > 10000 || $search['offset'] % 20 !== 0)) {
            throw new \RuntimeException(__('Skriv et kort arrangementsnavn og bruk sidelenkene for flere treff.', 'reginor-lite'), 400);
        }
        if ($eventId !== null && ($eventId < 1 || $eventId > 2147483647)) {
            throw new \RuntimeException(__('Skriv inn arrangementets numeriske ID fra LetsReg, for eksempel 12345.', 'reginor-lite'), 400);
        }
        if (Mutation::active()) { throw new \RuntimeException(__('Vent til kursendringen er ferdig før du kontrollerer tilkoblingen.', 'reginor-lite'), 409); }
        $config = self::config();
        if (!$config) { throw new \RuntimeException(__('API-tilgangen må settes opp på serveren først. Se veiledningen på siden.', 'reginor-lite'), 400); }
        global $wpdb;
        // Separate connection lock: token attempts do not hold the global course-writing lock.
        $lock = 'rnl_auth:' . md5(DB_NAME . ':' . $wpdb->prefix);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) {
            throw new \RuntimeException(__('En tilkoblingskontroll pågår allerede. Vent litt og oppdater siden.', 'reginor-lite'), 409);
        }
        try {
            $now = time(); $fingerprint = self::fingerprint($config); $old = get_option(self::OPTION, []);
            if (!is_array($old) || !hash_equals($fingerprint, (string) ($old['fingerprint'] ?? ''))) { $old = []; }
            $poll = get_option('rnl_letsreg_poll', []);
            if (($poll['fingerprint'] ?? '') === $fingerprint && !empty($poll['error'])
                && !($poll['blocked'] ?? false) && ($poll['next_at'] ?? 0) > $now) {
                throw new \RuntimeException(__('LetsReg-kontrollen venter etter en API-feil. Prøv igjen når ventetiden er over.', 'reginor-lite'), 429);
            }
            if ((int) ($old['next_attempt_at'] ?? 0) > $now && !($interactive && ($old['state'] ?? '') === 'verified')) {
                throw new \RuntimeException(__('Vent til neste tillatte kontroll. Tidspunktet vises på siden.', 'reginor-lite'), 429);
            }
            // A successful search must allow selecting its result immediately. Bound the whole
            // account's request rate instead of requiring users to wait between workflow steps.
            $window = (int) ($old['request_window'] ?? 0);
            $attempts = (int) ($old['request_count'] ?? 0);
            if ($window + 60 <= $now) { $window = $now; $attempts = 0; }
            if ($attempts >= 10) { throw new \RuntimeException(__('Mange oppslag på kort tid. Vent et minutt og prøv igjen; valgene dine beholdes.', 'reginor-lite'), 429); }
            $state = ['fingerprint' => $fingerprint, 'state' => 'error', 'stage' => 'token', 'error' => 'interrupted',
                'last_attempt_at' => $now, 'last_success_at' => $old['last_success_at'] ?? null,
                'next_attempt_at' => $now + 60, 'failures' => (int) ($old['failures'] ?? 0), 'checked_by' => get_current_user_id(),
                'request_window' => $window, 'request_count' => $attempts + 1];
            self::save($state); // A crash cannot leave an old success looking like a completed new check.
            $result = self::verify($config, $eventId, $search);
            $current = self::config();
            if (!$current || !hash_equals($fingerprint, self::fingerprint($current))) {
                throw new \RuntimeException(__('API-oppsettet ble endret under kontrollen. Kontroller det nye oppsettet.', 'reginor-lite'), 409);
            }
            $state['stage'] = $result['stage']; $state['error'] = $result['error'];
            if ($result['error'] === null) {
                $state['state'] = 'verified'; $state['last_success_at'] = time(); $state['verified_until'] = time() + 900;
                $state['organizer_name'] = $result['organizer_name']; $state['failures'] = 0;
                $state['verification_id'] = wp_generate_uuid4();
                if ($interactive) { $state['next_attempt_at'] = time(); }
                if (isset($result['event'])) {
                    $state['event'] = $result['event'];
                    LetsRegAvailabilityStore::capture(self::identity(), $eventId, $result['availability']);
                    SalesHistory::capture(self::identity(), $eventId, $result['sales']);
                    LetsRegChanges::capture(self::identity(), $eventId, $result['event']);
                }
                delete_option('rnl_letsreg_poll'); // A successful explicit check resumes a halted poller.
                if (isset($result['search'])) { $state['search'] = $result['search']; }
            } else {
                if ($eventId !== null) { LetsRegAvailabilityStore::failure(self::identity(), $eventId); }
                $state['failures'] = min(20, $state['failures'] + 1);
                $wait = Retry::delay($state['failures'], random_int(0, 30), $result['retry_after'] ?? null);
                if (in_array($result['error'], ['authentication', 'permission'], true)) { $wait = max(300, $wait); }
                $finished = time();
                $state['next_attempt_at'] = max($state['next_attempt_at'], $finished + min($wait, PHP_INT_MAX - $finished));
            }
            self::save($state);
            return self::state();
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }

    /** Internal account binding for observations; never contains credentials. */
    public static function identity(): ?array
    {
        $config = self::config();
        return $config ? ['fingerprint' => self::fingerprint($config), 'affiliate_id' => $config['affiliate_id'], 'organizer_id' => $config['organizer_id']] : null;
    }

    /** One short-lived token per bounded batch; it is discarded even if a job throws. */
    public static function pollBatch(array $eventIds): void
    {
        self::$pollingBatch = true; self::$batchToken = null;
        try {
            foreach (array_slice(array_unique($eventIds), 0, 3) as $eventId) { self::poll((int) $eventId); }
        } finally { self::$batchToken = null; self::$pollingBatch = false; }
    }

    /** Bounded server-side polling, sharing the interactive account lock and its backoff. */
    public static function poll(int $eventId): void
    {
        if (Mutation::active()) { throw new \RuntimeException('Polling cannot run under the course lock.'); }
        $config = self::config(); if (!$config || $eventId < 1 || $eventId > 2147483647) { return; }
        global $wpdb;
        $lock = 'rnl_auth:' . md5(DB_NAME . ':' . $wpdb->prefix);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) { return; }
        try {
            $identity = self::identity(); $now = time();
            $old = get_option('rnl_letsreg_poll', []);
            if (($old['fingerprint'] ?? '') !== $identity['fingerprint']) { $old = []; }
            $manual = get_option(self::OPTION, []);
            if (($manual['fingerprint'] ?? '') !== $identity['fingerprint']) { $manual = []; }
            if (($old['blocked'] ?? false) || ($old['next_at'] ?? 0) > $now || ($manual['next_attempt_at'] ?? 0) > $now) { return; }
            if (in_array($manual['error'] ?? null, ['authentication', 'permission'], true)) { return; }
            $window = ($old['window'] ?? 0) + 60 > $now ? $old['window'] : $now;
            $count = $window === ($old['window'] ?? 0) ? ($old['count'] ?? 0) : 0;
            if ($count >= 3) { return; }
            $state = ['window' => $window, 'count' => $count + 1, 'fingerprint' => $identity['fingerprint'], 'next_at' => $now + 60, 'blocked' => false, 'error' => 'interrupted', 'failures' => $old['failures'] ?? 0];
            self::savePoll($state);
            $result = self::verify($config, $eventId, null, self::$pollingBatch);
            if (self::identity() !== $identity) { return; }
            $state['error'] = $result['error']; $state['stage'] = $result['stage'];
            if ($result['error'] !== null) { self::$batchToken = null; }
            if ($result['error'] === null) {
                $state['failures'] = 0; $state['next_at'] = $state['count'] >= 3 ? $window + 60 : time();
                if (self::$pollingBatch && (!self::$batchToken || self::$batchToken['expires_at'] <= time() + 40)) { $state['next_at'] = time() + 60; }
                LetsRegAvailabilityStore::capture($identity, $eventId, $result['availability']);
                SalesHistory::capture($identity, $eventId, $result['sales']);
                LetsRegChanges::capture($identity, $eventId, $result['event']);
            } else {
                $state['failures'] = min(20, $state['failures'] + 1);
                $state['blocked'] = in_array($result['error'], ['authentication', 'permission'], true);
                $state['next_at'] = time() + Retry::delay($state['failures'], random_int(0, 30), $result['retry_after'] ?? null);
                LetsRegAvailabilityStore::failure($identity, $eventId);
            }
            self::savePoll($state);
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }

    private static function savePoll(array $state): void
    {
        if (!update_option('rnl_letsreg_poll', $state, false) && get_option('rnl_letsreg_poll') !== $state) {
            throw new \RuntimeException(__('Kontrollkøen kunne ikke lagres. Prøv igjen senere.', 'reginor-lite'), 503);
        }
    }

    private static function verify(#[\SensitiveParameter] array $config, ?int $eventId, ?array $search, bool $reuseToken = false): array
    {
        $cached = $reuseToken ? self::$batchToken : null;
        if ($cached && $cached['fingerprint'] === self::fingerprint($config) && $cached['expires_at'] > time() + 40) {
            $token = $cached['token']; $expiresAt = $cached['expires_at'];
        } else {
            if ($reuseToken) { self::$batchToken = null; }
            $body = ['grant_type' => 'password', 'username' => $config['affiliate_id'] . ':' . $config['username'], 'password' => $config['password']];
            if ($config['client_id'] !== '') { $body['client_id'] = $config['client_id']; }
            $started = time();
            try {
                $response = wp_safe_remote_post(self::TOKEN_URL, ['body' => http_build_query($body, '', '&', PHP_QUERY_RFC1738),
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'],
                    'timeout' => 10, 'redirection' => 0, 'sslverify' => true, 'cookies' => [],
                    'limit_response_size' => ReadOnlyApiTransport::MAX_BYTES + 1, 'user-agent' => 'RegiNor-Lite/0.1.0']);
            } catch (\Throwable) { return ['stage' => 'token', 'error' => 'transport']; }
            $result = ReadOnlyApiTransport::decodeResponse($response);
            if ($result['error'] !== null) {
                return ['stage' => 'token', 'error' => $result['status'] === 400 ? 'authentication' : $result['error'], 'retry_after' => $result['retry_after']];
            }
            $data = $result['data'];
            $token = $data['access_token'] ?? null;
            if (!is_string($token) || !preg_match('#\A[A-Za-z0-9._~+/-]+=*\z#', $token) || strlen($token) > 8000
                || !is_string($data['token_type'] ?? null) || strcasecmp($data['token_type'], 'bearer') !== 0) {
                return ['stage' => 'token', 'error' => 'invalid_token'];
            }
            $expiresAt = null;
            if (array_key_exists('expires_in', $data)) {
                if (!is_int($data['expires_in']) && !is_string($data['expires_in'])) { return ['stage' => 'token', 'error' => 'invalid_token']; }
                $expiry = filter_var($data['expires_in'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                if ($expiry === false || $started + $expiry <= time() + 10) { return ['stage' => 'token', 'error' => 'invalid_token']; }
                $expiresAt = $started + $expiry;
            }
            if ($reuseToken && $expiresAt !== null) {
                self::$batchToken = ['fingerprint' => self::fingerprint($config), 'token' => $token, 'expires_at' => $expiresAt];
            }
        }
        // Tokens live only in this check or bounded polling batch, never persistent storage.
        $transport = new ReadOnlyApiTransport(self::ORIGIN);
        $headers = ['Authorization' => 'Bearer ' . $token];
        $result = $transport->get('/organizers/' . $config['organizer_id'], $headers);
        if ($result['error'] !== null) { return ['stage' => 'organizer', 'error' => $result['error'], 'retry_after' => $result['retry_after']]; }
        $organizer = $result['data'];
        if (($organizer['id'] ?? null) !== $config['organizer_id'] || ($organizer['affiliateId'] ?? null) !== $config['affiliate_id']) {
            return ['stage' => 'organizer', 'error' => 'organization_mismatch'];
        }
        $name = $organizer['name'] ?? '';
        if (!is_string($name) || strlen($name) > 500) { return ['stage' => 'organizer', 'error' => 'invalid_json']; }
        $verified = ['stage' => 'organizer', 'error' => null, 'organizer_name' => sanitize_text_field($name)];
        if ($search !== null) {
            if ($expiresAt !== null && $expiresAt <= time() + 10) { return ['stage' => 'search', 'error' => 'invalid_token']; }
            $query = http_build_query(['Query' => $search['query'], 'Offset' => $search['offset'], 'Limit' => 20,
                'IncludeFields' => 'false', 'IncludeInstructors' => 'false', 'IncludeCancellationInfo' => 'false'], '', '&', PHP_QUERY_RFC3986);
            $result = $transport->get('/organizers/' . $config['organizer_id'] . '/events?' . $query, $headers);
            if ($result['error'] !== null) { return ['stage' => 'search', 'error' => $result['error'], 'retry_after' => $result['retry_after']]; }
            $events = $result['json_list'] ? LetsRegEventInspection::search($result['data'], $config['organizer_id'], $config['affiliate_id']) : null;
            if ($events === null) { return ['stage' => 'search', 'error' => 'invalid_events']; }
            return array_replace($verified, ['stage' => 'search', 'search' => $search + ['events' => $events, 'has_more' => count($events) === 20]]);
        }
        if ($eventId === null) { return $verified; }
        if ($expiresAt !== null && $expiresAt <= time() + 10) { return ['stage' => 'event', 'error' => 'invalid_token']; }
        $result = $transport->get('/events/' . $eventId, $headers);
        if ($result['error'] !== null) { return ['stage' => 'event', 'error' => $result['status'] === 404 ? 'event_missing' : $result['error'], 'retry_after' => $result['retry_after']]; }
        $rawEvent = $result['data'];
        $event = LetsRegEventInspection::event($result['data'], $eventId, $config['organizer_id'], $config['affiliate_id']);
        if ($event === null) { return ['stage' => 'event', 'error' => 'event_mismatch']; }
        if ($expiresAt !== null && $expiresAt <= time() + 10) { return ['stage' => 'prices', 'error' => 'invalid_token']; }
        $result = $transport->get('/events/' . $eventId . '/prices', $headers);
        if ($result['error'] !== null) { return ['stage' => 'prices', 'error' => $result['error'], 'retry_after' => $result['retry_after']]; }
        $prices = $result['json_list'] ? LetsRegEventInspection::prices($result['data']) : null;
        if ($prices === null) { return ['stage' => 'prices', 'error' => 'invalid_prices']; }
        return array_replace($verified, ['stage' => 'prices', 'event' => $event + ['prices' => $prices],
            'sales' => \RegiNor\Lite\Domain\Analytics\SalesObservation::fromEvent($rawEvent),
            'availability' => \RegiNor\Lite\Domain\Publication\LetsRegAvailability::observation($rawEvent, $result['data'])]);
    }
}
