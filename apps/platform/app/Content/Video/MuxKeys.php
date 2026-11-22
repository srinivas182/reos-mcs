<?php

declare(strict_types=1);

namespace App\Content\Video;

/** Mux keys for the mode in use (Settings › Integrations › Mux Video). */
final readonly class MuxKeys
{
    public function __construct(
        public string $tokenId,
        public string $secret,
        public string $webhookSecret,
        public string $signingKeyId,
        public string $signingPrivateKey,
    ) {}

    /** The signing key as PEM; Mux shows it base64-encoded. */
    public function privateKeyPem(): string
    {
        $key = trim($this->signingPrivateKey);

        if (str_starts_with($key, '-----BEGIN')) {
            return $key;
        }

        $decoded = base64_decode($key, true);

        return $decoded === false ? $key : $decoded;
    }
}
