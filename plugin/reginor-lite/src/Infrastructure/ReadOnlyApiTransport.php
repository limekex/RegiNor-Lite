<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

/** Provider-neutral transport. No endpoint, auth scheme or live adapter is assumed. */
final class ReadOnlyApiTransport
{
    public const MAX_BYTES = 262144;
    private readonly string $origin;

    /** The origin must come from a reviewed provider contract, never from a course or request URL. */
    public function __construct(string $origin)
    {
        $parts = parse_url($origin);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts))
            || !in_array($parts['path'] ?? '', ['', '/'], true) || (isset($parts['port']) && $parts['port'] !== 443)
            || !preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}\z/i', $parts['host'] ?? '')
            || strlen($origin) > 300) {
            throw new \InvalidArgumentException('Invalid API origin');
        }
        $this->origin = 'https://' . strtolower($parts['host']);
    }

    /**
     * A single request, without automatic retries or persistence. Headers are supplied by a future
     * verified authentication adapter. Successful JSON is still untrusted provider data: its
     * organization, complete pagination and capacity meaning must be checked before storage.
     */
    public function get(string $path, #[\SensitiveParameter] array $headers = []): array
    {
        if (Mutation::active()) { return self::failure('local_lock'); }
        [$pathname, $query] = array_pad(explode('?', $path, 2), 2, '');
        $decoded = rawurldecode($pathname);
        if (strlen($path) > 2048 || !str_starts_with($path, '/') || str_starts_with($decoded, '//')
            || preg_match('/[\x00-\x20\x7f\\\\#]/', $path) || preg_match('/[\x00-\x1f\x7f]/', rawurldecode($query))
            || preg_match('/[\x00-\x20\x7f\\\\#]/', $decoded) || str_contains($decoded, '://')
            || preg_match('~(?:^|/)\.{1,2}(?:/|\?|$)~', $decoded)) {
            return self::failure('invalid_request');
        }
        $requestHeaders = ['accept' => 'application/json'];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !preg_match('/\A[a-zA-Z][a-zA-Z0-9-]*\z/', $name)
                || !is_string($value) || strlen($value) > 8192 || preg_match('/[\x00-\x1f\x7f]/', $value)
                || in_array(strtolower($name), ['host', 'cookie', 'content-length', 'transfer-encoding', 'connection', 'proxy-authorization'], true)) {
                return self::failure('invalid_request');
            }
            $requestHeaders[strtolower($name)] = $value;
        }
        try {
            $response = wp_safe_remote_get($this->origin . $path, [
                'headers' => $requestHeaders, 'timeout' => 10, 'redirection' => 0,
                'sslverify' => true, 'cookies' => [], 'limit_response_size' => self::MAX_BYTES + 1,
                'user-agent' => 'RegiNor-Lite/0.1.0',
            ]);
        } catch (\Throwable) { return self::failure('transport', 0, true); }
        return self::decodeResponse($response);
    }

    /** Shared bounded JSON/error handling for GET and the fixed LetsReg token exchange. */
    public static function decodeResponse(#[\SensitiveParameter] mixed $response): array
    {
        // Never return raw WP_Error messages, response headers, URLs or error bodies to callers.
        if (is_wp_error($response)) { return self::failure('transport', 0, true); }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status === 401) { return self::failure('authentication', $status); }
        if ($status === 403) { return self::failure('permission', $status); }
        if ($status === 429 || ($status >= 500 && $status <= 599)) {
            return self::failure($status === 429 ? 'rate_limit' : 'temporary', $status, true,
                self::retryAfter(wp_remote_retrieve_header($response, 'retry-after'), time()));
        }
        if ($status >= 300 && $status <= 399) { return self::failure('redirect', $status); }
        if ($status < 200 || $status >= 300 || $status === 206) { return self::failure('response', $status); }
        $body = wp_remote_retrieve_body($response);
        if (strlen($body) > self::MAX_BYTES) { return self::failure('response_too_large', $status); }
        $type = wp_remote_retrieve_header($response, 'content-type');
        if (!is_string($type) || !preg_match('~^application/(?:[a-z0-9.+-]+\+)?json(?:\s*;|\s*$)~i', $type)) { return self::failure('invalid_json', $status); }
        try { $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING); }
        catch (\JsonException) { return self::failure('invalid_json', $status); }
        if (!is_array($data)) { return self::failure('invalid_json', $status); }
        return ['status' => $status, 'error' => null, 'retryable' => false, 'retry_after' => null, 'data' => $data,
            'json_list' => str_starts_with(ltrim($body), '[')];
    }

    private static function failure(string $error, int $status = 0, bool $retryable = false, ?int $retryAfter = null): array
    {
        return ['status' => $status, 'error' => $error, 'retryable' => $retryable, 'retry_after' => $retryAfter, 'data' => null];
    }

    private static function retryAfter(mixed $raw, int $now): ?int
    {
        if (!is_string($raw)) { return null; }
        $raw = trim($raw);
        if (ctype_digit($raw)) {
            $seconds = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX - $now]]);
            return $seconds === false ? null : $seconds;
        }
        $format = 'D, d M Y H:i:s \G\M\T';
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $raw, new \DateTimeZone('UTC'));
        return $date && $date->format($format) === $raw ? max(0, $date->getTimestamp() - $now) : null;
    }
}
