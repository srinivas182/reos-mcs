<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Messaging\SimulatorTextMessages;
use App\Models\IntegrationSetting;
use App\Models\State;
use App\Models\User;
use App\Notifications\StateWentLive;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Privacy\Consents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->state = State::query()->firstOrCreate(['code' => 'LA'], ['name' => 'Louisiana', 'status' => 'live']);
    $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00', 'America/Chicago'));
});

/** @return list<array{to: string, body: string, at: string}> */
function texts(): array
{
    return app(SimulatorTextMessages::class)->outbox();
}

/** Sends a code to the number and returns it, read from the simulator's log line. */
function sendCode(object $test, string $phone): string
{
    $code = '';
    Log::listen(function ($message) use (&$code) {
        if (preg_match('/code for .*: (\d{6})/', $message->message, $m) === 1) {
            $code = $m[1];
        }
    });
    $test->actingAs($test->james)->post('/settings/notifications/phone', ['phone' => $phone])->assertSessionHasNoErrors();

    return $code;
}

function withVerifiedNumber(User $user, string $phone = '+15125550100'): User
{
    $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();

    return $user;
}

describe('the mobile number', function () {
    it('verifies a US number with a code before texts can be turned on', function () {
        $this->actingAs($this->james)->put('/settings/notifications/texts', ['on' => true])->assertSessionHasErrors('on');

        $this->actingAs($this->james)->post('/settings/notifications/phone', ['phone' => '+447700900123'])->assertSessionHasErrors('phone');
        $code = sendCode($this, '+15125550100');
        $this->actingAs($this->james)->post('/settings/notifications/phone/verify', ['phone' => '+15125550100', 'code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->actingAs($this->james)->post('/settings/notifications/phone/verify', ['phone' => '+15125550100', 'code' => $code])->assertSessionHasNoErrors();

        expect($this->james->fresh()?->phone)->toBe('+15125550100')
            ->and(DB::table('audit_events')->where('action', 'account.phone_changed')->exists())->toBeTrue();
    });

    it('refuses a number that belongs to someone else', function () {
        withVerifiedNumber(User::factory()->create(), '+15125550100');

        $this->actingAs($this->james, 'sanctum')->postJson('/api/v1/me/phone', ['phone' => '+15125550100'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    });
});

describe('consent', function () {
    it('done when: texts go only with recorded consent, and STOP ends them', function () {
        withVerifiedNumber($this->james);

        // A text-only choice without consent sends nothing.
        $this->actingAs($this->james)->put('/settings/notifications', ['channels' => ['state_went_live' => ['sms' => true]]])->assertSessionHasNoErrors();
        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(texts())->toBe([]);

        // Turning texts on records consent and sends the confirmation text.
        $this->actingAs($this->james)->put('/settings/notifications/texts', ['on' => true])->assertSessionHasNoErrors();
        $consent = app(Consents::class)->latest($this->james, ConsentPurpose::Sms);
        expect($consent?->granted)->toBeTrue()->and($consent?->method)->toBe(ConsentMethod::Web)
            ->and(texts()[0]['body'])->toContain('Reply HELP for help, STOP to cancel');

        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(texts()[0])->toMatchArray(['to' => '+15125550100'])
            ->and(texts()[0]['body'])->toBe('REOS: REOS is now live in Louisiana. Reply STOP to stop.');

        // STOP by text withdraws consent; nothing more goes.
        $this->post('/webhooks/twilio-sms', ['From' => '+15125550100', 'Body' => 'Stop'])->assertOk()->assertSee('<Response></Response>', false);
        expect(app(Consents::class)->latest($this->james, ConsentPurpose::Sms))->toMatchArray(['granted' => false])
            ->and(app(Consents::class)->latest($this->james, ConsentPurpose::Sms)?->method)->toBe(ConsentMethod::Sms);

        $before = count(texts());
        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(texts())->toHaveCount($before)
            ->and(DB::table('notification_deliveries')->where('channel', 'sms')->pluck('status')->all())->toBe(['sent']);

        // START gives it back.
        $this->post('/webhooks/twilio-sms', ['From' => '+15125550100', 'Body' => 'START'])->assertOk();
        expect(app(Consents::class)->latest($this->james, ConsentPurpose::Sms)?->granted)->toBeTrue();
    });

    it('answers HELP', function () {
        $this->post('/webhooks/twilio-sms', ['From' => '+15125550100', 'Body' => 'help'])->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=utf-8')
            ->assertSee('<Message>REOS alerts: get help at reos.com/help', false);
    });

    it('turns texts on from the phone, and from Privacy too', function () {
        withVerifiedNumber($this->james);

        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/texts', ['on' => true])->assertOk()
            ->assertJson(['phone' => '••• ••• 0100', 'on' => true, 'available' => true]);
        expect(app(Consents::class)->latest($this->james, ConsentPurpose::Sms)?->method)->toBe(ConsentMethod::App);

        $this->actingAs($this->james)->put('/settings/privacy/consents/sms', ['granted' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/me/notification-settings')->assertJsonPath('texts.on', false);
    });

    it('never texts locked kinds, and holds texts in quiet hours like other alerts', function () {
        withVerifiedNumber($this->james);
        app(Consents::class)->grant($this->james, ConsentPurpose::Sms, ConsentMethod::Web);
        $this->actingAs($this->james)->put('/settings/notifications', ['channels' => ['state_went_live' => ['sms' => true]]])->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-11-24 23:00', 'America/Chicago'));
        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(texts())->toBe([]);

        $this->travelTo(CarbonImmutable::parse('2026-11-25 07:05', 'America/Chicago'));
        $this->artisan('reos:notifications:release')->assertSuccessful();
        expect(texts())->toHaveCount(1);

        $this->actingAs($this->james)->put('/settings/notifications', ['channels' => ['new_device' => ['sms' => true]]])->assertSessionHasErrors('channels');
    });
});

describe('Twilio', function () {
    it('accepts only replies signed with the auth token once Twilio is set up', function () {
        IntegrationSetting::query()->create(['integration_id' => 'twilio-sms', 'mode' => IntegrationMode::Sandbox]);
        app(CredentialVault::class)->put('twilio-sms', CredentialEnvironment::Sandbox, ['account_sid' => 'ACtest', 'auth_token' => 'secret-token', 'from_number' => '+15125550199'], null);
        withVerifiedNumber($this->james);
        app(Consents::class)->grant($this->james, ConsentPurpose::Sms, ConsentMethod::Web);

        $fields = ['Body' => 'STOP', 'From' => '+15125550100'];
        $this->post('/webhooks/twilio-sms', $fields, ['X-Twilio-Signature' => 'forged'])->assertForbidden();
        expect(app(Consents::class)->latest($this->james, ConsentPurpose::Sms)?->granted)->toBeTrue();

        $url = url('/webhooks/twilio-sms');
        $signature = base64_encode(hash_hmac('sha1', $url.'BodySTOPFrom+15125550100', 'secret-token', true));
        $this->post('/webhooks/twilio-sms', $fields, ['X-Twilio-Signature' => $signature])->assertOk();
        expect(app(Consents::class)->latest($this->james, ConsentPurpose::Sms)?->granted)->toBeFalse();
    });

    it('sends through Twilio with the saved number', function () {
        IntegrationSetting::query()->create(['integration_id' => 'twilio-sms', 'mode' => IntegrationMode::Sandbox]);
        app(CredentialVault::class)->put('twilio-sms', CredentialEnvironment::Sandbox, ['account_sid' => 'ACtest', 'auth_token' => 'secret-token', 'from_number' => '+15125550199'], null);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        withVerifiedNumber($this->james);

        $this->actingAs($this->james)->put('/settings/notifications/texts', ['on' => true])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'Accounts/ACtest/Messages.json') && $r['To'] === '+15125550100' && $r['From'] === '+15125550199');
    });
});
