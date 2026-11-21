<?php

declare(strict_types=1);

namespace App\Connections;

/**
 * Signed values for Simulator connections (codes, tokens, webhook bodies), so
 * the demo path is checked as strictly as the real one: a tampered code,
 * token or webhook is refused.
 */
final class SimulatorSignature
{
    /** @param  array<string, mixed>  $claims */
    public static function seal(array $claims): string
    {
        $body = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return "sim.{$body}.".self::sign($body);
    }

    /** @return array<string, mixed>|null */
    public static function open(string $value): ?array
    {
        $parts = explode('.', $value);

        if (count($parts) !== 3 || $parts[0] !== 'sim' || ! hash_equals(self::sign($parts[1]), $parts[2])) {
            return null;
        }

        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($claims)) {
            return null;
        }

        /** @var array<string, mixed> $claims */
        return $claims;
    }

    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', "reos-simulator:{$payload}", config()->string('app.key'));
    }
}
