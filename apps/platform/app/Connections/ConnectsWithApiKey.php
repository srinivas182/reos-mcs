<?php

declare(strict_types=1);

namespace App\Connections;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorUnavailable;

/**
 * A tool people connect with their own API key instead of signing in
 * (Follow Up Boss, kvCORE; B7.2, ADR 0028). The key is stored like an access
 * token (encrypted, never returned) and has no refresh: when the tool
 * refuses it, the connection needs a new key.
 */
interface ConnectsWithApiKey
{
    /**
     * Checks the key with the tool and says whose account it is.
     *
     * @throws AccessRevoked when the tool refuses the key.
     * @throws VendorUnavailable
     */
    public function accountForKey(string $apiKey): ConnectedAccount;
}
