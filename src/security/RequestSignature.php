<?php

declare(strict_types=1);

namespace mojo\observer\security;

/**
 * The signature observer puts on every request, and the deploy marker on its own.
 *
 * HMAC-SHA256, keyed with the site's OBSERVER_KEY, over five lines:
 *
 *     METHOD \n target \n unix timestamp \n nonce \n sha256(body)
 *
 * `target` is the request path (`/actions/observer/api/vitals`), or `deploy-marker` for the
 * marker. The nonce (16–64 hex characters, random per request) makes every signature unique, so a
 * retry within the same second is not mistaken for a replay. packages/jobs/src/craft/signature.ts
 * in observer computes the same string; both test suites pin one shared vector.
 */
final class RequestSignature
{
    /** How far a request's timestamp may be from this server's clock, either way. */
    public const MAX_SKEW_SECONDS = 300;

    public static function canonical(string $method, string $target, int $timestamp, string $nonce, string $body): string
    {
        return strtoupper($method) . "\n" . $target . "\n" . $timestamp . "\n" . strtolower($nonce) . "\n" . hash('sha256', $body);
    }

    public static function sign(string $key, string $method, string $target, int $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', self::canonical($method, $target, $timestamp, $nonce, $body), $key);
    }

    /** A fresh nonce for a request this side sends. */
    public static function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Null when the signature is valid and fresh; otherwise why not, for the 401 answer.
     */
    public static function verify(
        string $key,
        string $method,
        string $target,
        string $timestamp,
        string $nonce,
        string $signature,
        string $body,
        int $now,
    ): ?string {
        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return 'missing X-Observer-Timestamp, X-Observer-Nonce or X-Observer-Signature';
        }
        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1) {
            return 'malformed timestamp';
        }
        if (preg_match('/^[0-9a-f]{16,64}$/i', $nonce) !== 1) {
            return 'malformed nonce';
        }
        if (abs($now - (int)$timestamp) > self::MAX_SKEW_SECONDS) {
            return 'timestamp outside the allowed window — check both clocks';
        }
        if (!hash_equals(self::sign($key, $method, $target, (int)$timestamp, $nonce, $body), strtolower($signature))) {
            return 'invalid signature';
        }

        return null;
    }
}
