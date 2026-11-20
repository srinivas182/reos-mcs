<?php

declare(strict_types=1);

use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialCipher;
use App\Integrations\Credentials\CredentialVault;
use App\Models\IntegrationCredential;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses to store keys when the encryption key is missing', function () {
    $cipher = new CredentialCipher(null);

    expect(fn () => $cipher->encrypt('secret'))->toThrow(RuntimeException::class, 'REOS_CREDENTIALS_KEY is not set');
});

it('re-encrypts saved keys after the key is rotated', function () {
    $oldKey = config()->string('reos.integrations.credentials_key');
    app(CredentialVault::class)->put('synthesia', CredentialEnvironment::Live, ['api_key' => 'syn-live-123456789'], null);
    $before = IntegrationCredential::query()->sole()->payload;

    $newKey = CredentialCipher::generateKey();
    config([
        'reos.integrations.credentials_key' => $newKey,
        'reos.integrations.previous_credentials_keys' => [$oldKey],
    ]);
    app()->forgetInstance(CredentialCipher::class);
    app()->forgetInstance(CredentialVault::class);

    $this->artisan('reos:credentials:rekey')->expectsOutputToContain('Re-encrypted 1')->assertSuccessful();

    $after = IntegrationCredential::query()->sole()->payload;
    expect($after)->not->toBe($before)
        ->and((new CredentialCipher($newKey))->decrypt($after))->toBe('{"api_key":"syn-live-123456789"}')
        ->and(fn () => (new CredentialCipher($oldKey))->decrypt($after))->toThrow(DecryptException::class);
});

it('prints a new key for servers whose secrets live outside .env', function () {
    $this->artisan('reos:credentials:key --show')
        ->expectsOutputToContain('base64:')
        ->assertSuccessful();
});
