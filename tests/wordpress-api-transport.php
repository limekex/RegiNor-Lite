<?php
/** Contract-independent HTTP checks. Every request is intercepted; no provider is contacted. */
use RegiNor\Lite\Infrastructure\ReadOnlyApiTransport;
use RegiNor\Lite\Infrastructure\Mutation;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$checks = 0; $calls = []; $reply = [];
$assert = static function ($condition, $message) use (&$checks) { ++$checks; if (!$condition) throw new RuntimeException($message); };
$http = static function ($pre, $args, $url) use (&$calls, &$reply) {
    $calls[] = ['url' => $url, 'args' => $args]; return $reply;
};
$response = static fn ($status, $body = '{}', $headers = []) => ['response' => ['code' => $status], 'headers' => $headers + ['content-type' => 'application/json'], 'body' => $body];
add_filter('pre_http_request', $http, PHP_INT_MAX, 3);
try {
    foreach (['http://api.example.org', 'https://user:secret@api.example.org', 'https://api.example.org/?key=secret', 'https://api.example.org/#secret', 'https://api.example.org/v1', 'https://api.example.org:8443', 'https://localhost', 'https://127.0.0.1'] as $origin) {
        $rejected = false; try { new ReadOnlyApiTransport($origin); } catch (InvalidArgumentException) { $rejected = true; }
        $assert($rejected, 'Unsafe origin accepted');
    }
    $client = new ReadOnlyApiTransport('https://api.example.org');
    $reply = $response(200, '{"organization_id":"test-only","capacity":0}');
    $result = $client->get('/v1/test?page=1', ['Authorization' => 'Bearer synthetic-secret']);
    $assert($result['error'] === null && $result['data']['capacity'] === 0, 'Valid JSON or zero capacity lost');
    $request = $calls[0];
    $assert($request['url'] === 'https://api.example.org/v1/test?page=1' && $request['args']['headers']['authorization'] === 'Bearer synthetic-secret', 'Header or fixed origin not respected');
    $assert($request['args']['redirection'] === 0 && $request['args']['sslverify'] && $request['args']['reject_unsafe_urls'] && $request['args']['timeout'] === 10 && $request['args']['limit_response_size'] === ReadOnlyApiTransport::MAX_BYTES + 1, 'Transport bounds missing');
    $before = count($calls);
    foreach (['https://evil.example/test', '//evil.example/test', '/%2fsecret', '/test%0d%0aHost:evil', '/test\\evil', '/../secret', '/%2e%2e/secret', '/test#fragment', '/test%20path', '/test?q=raw space', '/test?q=bad%0d%0aHeader', '/test?q=x#fragment'] as $path) {
        $assert($client->get($path)['error'] === 'invalid_request', 'Unsafe path accepted');
    }
    foreach ([['Authorization' => "secret\r\nOther: injected"], ['Host' => 'evil.example'], ['Cookie' => 'secret'], ['X-Key' => ['secret']]] as $headers) {
        $assert($client->get('/test', $headers)['error'] === 'invalid_request', 'Unsafe header accepted');
    }
    $assert(count($calls) === $before, 'Rejected request reached HTTP');
    $assert(Mutation::run(static fn () => $client->get('/test'))['error'] === 'local_lock' && count($calls) === $before, 'Network I/O occurred under global course lock');
    foreach ([401 => 'authentication', 403 => 'permission', 302 => 'redirect', 404 => 'response', 206 => 'response', 429 => 'rate_limit', 503 => 'temporary'] as $status => $error) {
        $reply = $response($status, '{"secret":"must-not-leak"}', ['location' => 'https://evil.example/secret', 'retry-after' => '600']);
        $before = count($calls); $result = $client->get('/test');
        $assert($result['error'] === $error && $result['data'] === null && count($calls) === $before + 1 && !str_contains(json_encode($result), 'must-not-leak'), 'Error leaks data, retries automatically or is misclassified');
        $assert($result['retryable'] === in_array($status, [429, 503], true), 'Access failure is automatically retryable');
        if ($status === 429 || $status === 503) $assert($result['retry_after'] === 600, 'Provider cooldown lost');
    }
    $reply = $response(429, '{}', ['retry-after' => gmdate('D, d M Y H:i:s \G\M\T', time() + 600)]);
    $delay = $client->get('/test')['retry_after']; $assert($delay >= 598 && $delay <= 600, 'HTTP-date Retry-After lost');
    foreach (['tomorrow', '-1', '6.5', 'secret'] as $invalid) {
        $reply = $response(429, '{}', ['retry-after' => $invalid]);
        $assert($client->get('/test')['retry_after'] === null, 'Malformed Retry-After accepted');
    }
    $reply = new WP_Error('http_request_failed', 'URL and synthetic-secret must not leak');
    $result = $client->get('/test');
    $assert($result['error'] === 'transport' && $result['retryable'] && !str_contains(json_encode($result), 'synthetic-secret'), 'WP error exposes secrets');
    foreach (['<html>Not JSON</html>', '{"partial":', 'null', 'true'] as $body) {
        $reply = $response(200, $body); $assert($client->get('/test')['error'] === 'invalid_json', 'Invalid JSON accepted');
    }
    $reply = $response(200, '{}', ['content-type' => 'text/html']);
    $assert($client->get('/test')['error'] === 'invalid_json', 'HTML login response accepted');
    $reply = $response(200, '{"large_id":9223372036854775808}');
    $assert($client->get('/test')['data']['large_id'] === '9223372036854775808', 'Large provider identifier loses precision');
    $reply = $response(200, str_repeat(' ', ReadOnlyApiTransport::MAX_BYTES + 1));
    $assert($client->get('/test')['error'] === 'response_too_large', 'Truncated/oversized response accepted');
} finally { remove_filter('pre_http_request', $http, PHP_INT_MAX); }
WP_CLI::success("$checks API transport checks passed; no external requests sent.");
