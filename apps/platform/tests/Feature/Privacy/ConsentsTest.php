<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\AuditEvent;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Identity\AccountResolver;
use App\Services\Privacy\Consents;
use App\Services\Privacy\DataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->consents = app(Consents::class);
    $this->maria = User::factory()->withRole(Role::Candidate)->create(['name' => 'Maria Lopez']);
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
});

describe('signing up', function () {
    it('needs the terms ticked on the web, and records the terms and privacy notice with their version', function () {
        $this->post('/register', ['name' => 'Rosa Diaz', 'email' => 'rosa@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('terms');

        $this->post('/register', ['name' => 'Rosa Diaz', 'email' => 'rosa@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'terms' => '1'])
            ->assertRedirect();

        $rosa = User::query()->where('email', 'rosa@example.com')->firstOrFail();
        $records = ConsentRecord::query()->where('user_id', $rosa->id)->orderBy('id')->get();

        expect($records->pluck('purpose')->all())->toBe([ConsentPurpose::Terms, ConsentPurpose::PrivacyNotice])
            ->and($records->every(fn (ConsentRecord $r) => $r->granted && $r->method === ConsentMethod::Web && $r->version === ConsentPurpose::Terms->version()))->toBeTrue();
    });

    it('records the same when an account is made with a texted code', function () {
        ['user' => $user, 'created' => $created] = app(AccountResolver::class)->fromPhone('+15125550199');

        expect($created)->toBeTrue()
            ->and($this->consents->latest($user, ConsentPurpose::Terms)?->granted)->toBeTrue()
            ->and($this->consents->latest($user, ConsentPurpose::PrivacyNotice)?->granted)->toBeTrue();

        // Signing in again records nothing new.
        app(AccountResolver::class)->fromPhone('+15125550199');
        expect(ConsentRecord::query()->where('user_id', $user->id)->count())->toBe(2);
    });
});

describe('the check before acting', function () {
    it('uses the latest record, else the default for the role', function () {
        expect($this->consents->allows($this->maria, ConsentPurpose::ProviderNetwork))->toBeTrue()
            // Agents are in the provider network until they turn it off (S16 decision 4).
            ->and($this->consents->allows($this->james, ConsentPurpose::ProviderNetwork))->toBeTrue()
            ->and($this->consents->allows($this->james, ConsentPurpose::Recruiting))->toBeFalse()
            ->and($this->consents->allows($this->maria, ConsentPurpose::Recruiting))->toBeFalse()
            ->and($this->consents->allows($this->maria, ConsentPurpose::Sms))->toBeFalse()
            ->and($this->consents->allows($this->maria, ConsentPurpose::SessionRecording, 'session:4'))->toBeFalse();

        $this->consents->grant($this->maria, ConsentPurpose::SessionRecording, ConsentMethod::App, 'session:4');
        $this->consents->withdraw($this->maria, ConsentPurpose::ProviderNetwork, ConsentMethod::Web);

        expect($this->consents->allows($this->maria, ConsentPurpose::SessionRecording, 'session:4'))->toBeTrue()
            ->and($this->consents->allows($this->maria, ConsentPurpose::SessionRecording, 'session:5'))->toBeFalse()
            ->and($this->consents->allows($this->maria, ConsentPurpose::ProviderNetwork))->toBeFalse();
    });

    it('never edits or deletes a record', function () {
        $record = $this->consents->grant($this->maria, ConsentPurpose::Sms, ConsentMethod::App);

        expect(fn () => $record->forceFill(['granted' => false])->save())->toThrow(LogicException::class)
            ->and(fn () => $record->delete())->toThrow(LogicException::class);
    });
});

describe('Settings › Privacy (web)', function () {
    it('shows the switches with their defaults and your consents', function () {
        $this->consents->signedUp($this->maria);

        $this->actingAs($this->maria)->get('/settings/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/privacy')
            ->has('switches', 5)
            ->where('switches', fn ($switches) => collect($switches)->firstWhere('purpose', 'provider_network')['on'] === true
                && collect($switches)->firstWhere('purpose', 'recruiting')['on'] === false)
            ->has('records', 2)
            ->where('records.0.withdrawable', false)
            ->where('versions.terms', ConsentPurpose::Terms->version()));
    });

    it('turns a switch on and off, recording each choice', function () {
        $this->actingAs($this->maria)->put('/settings/privacy/consents/recruiting', ['granted' => true])->assertRedirect();
        $this->actingAs($this->maria)->put('/settings/privacy/consents/recruiting', ['granted' => true])->assertRedirect();
        $this->actingAs($this->maria)->put('/settings/privacy/consents/recruiting', ['granted' => false])->assertRedirect();

        $records = ConsentRecord::query()->where('user_id', $this->maria->id)->where('purpose', 'recruiting')->orderBy('id')->get();
        expect($records->pluck('granted')->all())->toBe([true, false])
            ->and($records->first()?->method)->toBe(ConsentMethod::Web)
            ->and($this->consents->allows($this->maria, ConsentPurpose::Recruiting))->toBeFalse();
    });

    it('keeps the terms until the account goes, and asks for one-off consents where they are used', function () {
        $this->actingAs($this->maria)->put('/settings/privacy/consents/terms', ['granted' => false])->assertSessionHasErrors('purpose');
        $this->actingAs($this->maria)->put('/settings/privacy/consents/session_recording', ['granted' => true, 'subject' => 'session:4'])->assertSessionHasErrors('subject');
        $this->actingAs($this->maria)->put('/settings/privacy/consents/sms', ['granted' => true, 'subject' => 'session:4'])->assertSessionHasErrors('subject');

        $this->consents->grant($this->maria, ConsentPurpose::SessionRecording, ConsentMethod::App, 'session:4');
        $this->actingAs($this->maria)->put('/settings/privacy/consents/session_recording', ['granted' => false, 'subject' => 'session:4'])->assertSessionHasNoErrors();
        expect($this->consents->allows($this->maria, ConsentPurpose::SessionRecording, 'session:4'))->toBeFalse();
    });

    it('is only for signed-in people', function () {
        $this->get('/settings/privacy')->assertRedirect('/login');
    });
});

describe('Me › Privacy (app)', function () {
    it('lists and changes switches, recorded as in the app', function () {
        // Texts need a verified number first (B6.2c).
        $this->james->forceFill(['phone' => '+15125550100', 'phone_verified_at' => now()])->save();

        $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/me/consents')->assertOk()
            ->assertJsonCount(5, 'switches')
            ->assertJsonPath('records', []);

        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/consents/sms', ['granted' => true])->assertOk()
            ->assertJsonPath('records.0.purpose', 'sms')
            ->assertJsonPath('records.0.method', 'app')
            ->assertJsonPath('records.0.withdrawable', true);

        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/consents/privacy_notice', ['granted' => false])->assertUnprocessable();
    });
});

describe('REOS on someone\'s behalf', function () {
    it('changes a switch with the person\'s request as the reason, and audits it', function () {
        $ops = User::factory()->superAdmin()->create();
        $confirmed = ['auth.password_confirmed_at' => now()->getTimestamp()];

        $this->actingAs($ops)->withSession($confirmed)->put("/admin/people/{$this->maria->id}/consents/marketing_email", ['granted' => false])->assertSessionHasErrors('note');
        $this->actingAs($ops)->withSession($confirmed)->put("/admin/people/{$this->maria->id}/consents/session_recording", ['granted' => false, 'note' => 'Asked by email'])->assertSessionHasErrors('purpose');
        $this->actingAs($ops)->withSession($confirmed)->put("/admin/people/{$this->maria->id}/consents/marketing_email", ['granted' => false, 'note' => 'Asked by email to stop news'])->assertRedirect();

        $record = $this->consents->latest($this->maria, ConsentPurpose::MarketingEmail);
        expect($record?->method)->toBe(ConsentMethod::Admin)
            ->and($record?->actor_id)->toBe($ops->id)
            ->and($record?->note)->toBe('Asked by email to stop news')
            ->and(AuditEvent::query()->where('action', 'privacy.consent_changed')->where('actor_id', $ops->id)->exists())->toBeTrue();

        $this->actingAs($ops)->withSession($confirmed)->get("/admin/people/{$this->maria->id}")->assertInertia(fn (Assert $page) => $page
            ->where('consents.records.0.by_admin', true)
            ->where('consents.records.0.method', 'admin'));
    });
});

describe('your data', function () {
    it('done when: a person downloads their data including their consents', function () {
        $this->consents->signedUp($this->maria);
        $this->consents->grant($this->maria, ConsentPurpose::Sms, ConsentMethod::App);
        $this->consents->withdraw($this->maria, ConsentPurpose::Sms, ConsentMethod::Web);

        $export = app(DataExport::class)->for($this->maria);

        expect($export['consents'])->toHaveCount(4)
            ->and($export['consents'][3])->toMatchArray(['purpose' => 'sms', 'granted' => false, 'method' => 'web', 'version' => ConsentPurpose::Sms->version()]);
    });

    it('keeps consent records as proof after the account is deleted, linked to no one', function () {
        $this->consents->signedUp($this->maria);
        $id = $this->maria->id;
        $this->maria->delete();

        expect(ConsentRecord::query()->whereNull('user_id')->count())->toBe(2)
            ->and(ConsentRecord::query()->where('user_id', $id)->count())->toBe(0);
    });
});

describe('the terms and the privacy notice', function () {
    it('are public, versioned and in the person\'s language', function () {
        $this->get('/terms')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/legal')
            ->where('title', 'Terms of use')
            ->where('version', ConsentPurpose::Terms->version())
            ->where('sections', fn ($sections) => count($sections) >= 5));

        $this->withUnencryptedCookie('locale', 'es')->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Aviso de privacidad')
            ->where('sections', fn ($sections) => collect($sections)->contains(fn ($s) => $s['heading'] === 'Mensajes de texto')));
    });
});
