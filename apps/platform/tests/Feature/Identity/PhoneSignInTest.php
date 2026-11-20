<?php

declare(strict_types=1);

use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\IntegrationCheck;
use App\Models\User;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

const PHONE = '+15125550100';

beforeEach(fn () => Http::preventStrayRequests());

function phoneDevice(): array
{
    return ['id' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'name' => 'Pixel 9', 'platform' => 'android'];
}

/** Sends a demo code and returns it (demo codes are written to the log). */
function demoCode(object $test, string $phone = PHONE): string
{
    $code = null;
    Log::listen(function ($message) use (&$code) {
        if (preg_match('/Sign-in code for .*: (\d{6})/', $message->message, $m) === 1) {
            $code = $m[1];
        }
    });

    $test->postJson('/api/v1/auth/phone/start', ['phone' => $phone])->assertStatus(202);

    return $code;
}

it('signs up with a phone number alone, with no email or password', function () {
    $code = demoCode($this);

    $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => $code, 'device' => phoneDevice()])
        ->assertOk()
        ->assertJsonPath('new_account', true)
        ->assertJsonPath('user.phone', PHONE)
        ->assertJsonPath('user.name', null)
        ->assertJsonPath('user.email', null)
        ->assertJsonPath('user.sign_in_methods', ['phone']);

    $user = User::query()->sole();
    expect($user->hasVerifiedEmail())->toBeTrue('a verified phone stands in for email');
});

it('signs the same person back in with a new code', function () {
    $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => demoCode($this), 'device' => phoneDevice()])->assertOk();
    $this->travel(2)->minutes();

    $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => demoCode($this), 'device' => phoneDevice()])
        ->assertOk()->assertJsonPath('new_account', false);

    expect(User::query()->count())->toBe(1);
});

it('locks the number for 15 minutes after five wrong codes', function () {
    demoCode($this);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => '000000', 'device' => phoneDevice()])
            ->assertUnprocessable()->assertJsonPath('reason', 'identity.errors.wrongCode');
    }

    $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => '123456', 'device' => phoneDevice()])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Too many wrong codes. Try again in 15 minutes.');

    expect(AuditEvent::query()->where('action', 'auth.sign_in_failed')->count())->toBe(5)
        ->and(AuditEvent::query()->first()?->subject_id)->toBe('••••0100');
});

it('codes can only be used once', function () {
    $code = demoCode($this);
    $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => $code, 'device' => phoneDevice()])->assertOk();

    $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => $code, 'device' => phoneDevice()])
        ->assertUnprocessable();
});

it('sends one code a minute per number, to stop text-message abuse', function () {
    $this->postJson('/api/v1/auth/phone/start', ['phone' => PHONE])->assertStatus(202);
    $this->postJson('/api/v1/auth/phone/start', ['phone' => PHONE])->assertTooManyRequests();
    $this->postJson('/api/v1/auth/phone/start', ['phone' => '+15125550199'])->assertStatus(202);
});

it('accepts only international phone numbers', function () {
    $this->postJson('/api/v1/auth/phone/start', ['phone' => '512-555-0100'])->assertJsonValidationErrors('phone');
});

it('uses Twilio Verify once it runs on real keys', function () {
    Http::fake([
        'verify.twilio.com/v2/Services/VA*/Verifications' => Http::response(['status' => 'pending'], 201),
        'verify.twilio.com/v2/Services/VA*/VerificationCheck' => Http::response(['status' => 'approved']),
    ]);
    $sid = 'VA'.str_repeat('a', 32);
    app(CredentialVault::class)->put('twilio-verify', CredentialEnvironment::Live, ['account_sid' => 'AC'.str_repeat('b', 32), 'auth_token' => 'fake', 'verify_service_sid' => $sid], null);
    IntegrationCheck::query()->create(['integration_id' => 'twilio-verify', 'environment' => 'live', 'outcome' => 'passed',
        'message' => 'integrations.checks.passed', 'parameters' => [], 'trigger' => 'manual', 'created_at' => now()->addMinute()]);
    app(IntegrationHub::class)->setMode(app(Catalogue::class)->find('twilio-verify'), IntegrationMode::Live, null);

    $this->withHeader('Accept-Language', 'es')->postJson('/api/v1/auth/phone/start', ['phone' => PHONE])->assertStatus(202);
    $this->postJson('/api/v1/auth/phone/verify', ['phone' => PHONE, 'code' => '482913', 'device' => phoneDevice()])->assertOk();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), "/Services/{$sid}/Verifications")
        && $request['To'] === PHONE && $request['Channel'] === 'sms');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/VerificationCheck') && $request['Code'] === '482913');
});
