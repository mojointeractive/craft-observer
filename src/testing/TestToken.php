<?php

declare(strict_types=1);

namespace mojo\observer\testing;

/**
 * The `X-Observer-Test` header that turns one request into an observer form test:
 *
 *     base64url(JSON payload) . "." . hex(HMAC-SHA256(OBSERVER_KEY, "form-test\n" . base64url(JSON payload)))
 *
 * The payload names the run (32 hex characters), the form adapter and form handle, the expiry (unix
 * seconds, at most ten minutes ahead), the mail mode (`redirect` or `copy`) and the probe address.
 * observer's packages/jobs/src/forms/token.ts signs the same string; both test suites pin one vector.
 */
final class TestToken
{
    public const MAX_LIFETIME_SECONDS = 600;

    public const MAIL_MODES = ['redirect', 'copy'];

    private function __construct(
        public readonly string $run,
        public readonly string $adapter,
        public readonly string $form,
        public readonly int $expires,
        public readonly string $mail,
        public readonly string $probe,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function sign(string $key, array $payload): string
    {
        $encoded = self::base64UrlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $encoded . '.' . hash_hmac('sha256', "form-test\n" . $encoded, $key);
    }

    /**
     * The token when the header is well formed, correctly signed and current; otherwise why not:
     * "malformed", "invalid signature", "expired" or "valid for more than 600 seconds".
     */
    public static function verify(string $key, string $header, int $now): self|string
    {
        $parts = explode('.', $header);
        if (count($parts) !== 2 || preg_match('/^[A-Za-z0-9_-]+$/', $parts[0]) !== 1 || preg_match('/^[0-9a-f]{64}$/', $parts[1]) !== 1) {
            return 'malformed';
        }
        [$encoded, $signature] = $parts;
        if (!hash_equals(hash_hmac('sha256', "form-test\n" . $encoded, $key), $signature)) {
            return 'invalid signature';
        }

        $token = self::fromPayload(json_decode(self::base64UrlDecode($encoded), true));
        if ($token === null) {
            return 'malformed';
        }
        if ($token->expires <= $now) {
            return 'expired';
        }
        if ($token->expires - $now > self::MAX_LIFETIME_SECONDS) {
            return 'valid for more than 600 seconds';
        }

        return $token;
    }

    private static function fromPayload(mixed $payload): ?self
    {
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1) {
            return null;
        }
        $run = $payload['run'] ?? null;
        $adapter = $payload['adapter'] ?? null;
        $form = $payload['form'] ?? null;
        $expires = $payload['exp'] ?? null;
        $mail = $payload['mail'] ?? null;
        $probe = $payload['probe'] ?? null;
        if (!is_string($run) || preg_match('/^[0-9a-f]{32}$/', $run) !== 1
            || !is_string($adapter) || preg_match('/^[a-z][a-z0-9-]*$/', $adapter) !== 1
            || !is_string($form) || preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $form) !== 1
            || !is_int($expires)
            || !in_array($mail, self::MAIL_MODES, true)
            || !is_string($probe) || filter_var($probe, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return new self($run, $adapter, $form, $expires, $mail, $probe);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        return (string)base64_decode(strtr($value, '-_', '+/'), true);
    }
}
