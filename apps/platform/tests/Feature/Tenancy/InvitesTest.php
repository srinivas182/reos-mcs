<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Messaging\SimulatorTextMessages;
use App\Models\AuditEvent;
use App\Models\IntegrationInterest;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\BrokerAsked;
use App\Notifications\InvitationSent;
use App\Notifications\SeatsRequested;
use App\Services\Authorization\Memberships;
use App\Services\Tenancy\Invitations;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Invitations, seats, several tenants and "Ask my broker" (A4.3a; web screens 32 and 41). */
const LS = 'http://lone-star.reos.com';

function inviteBrokerage(int $seats = 25): Organisation
{
    return Organisation::factory()->create(['name' => 'Lone Star Realty', 'slug' => 'lone-star', 'limits' => ['seats' => $seats, 'payer' => 'organisation']]);
}

function inviteBroker(Organisation $brokerage, string $email = 'linda@lonestar.example'): User
{
    return User::factory()->withRole(Role::Broker, $brokerage)->withTwoFactor()->create(['name' => 'Linda Park', 'email' => $email]);
}

function invitationFor(string $contact): Invitation
{
    return app(Tenancy::class)->asPlatform(fn () => Invitation::query()->where('email', $contact)->orWhere('phone', $contact)->latest('id')->firstOrFail());
}

/** The link the person received (the token is only in the message). */
function inviteLink(Invitation $invitation): string
{
    return app(Invitations::class)->link($invitation);
}

beforeEach(fn () => Notification::fake());

describe('sending invites', function () {
    it('invites one person by email, with the role, link, code and expiry, audited', function () {
        $lone = inviteBrokerage();
        $linda = inviteBroker($lone);

        $this->actingAs($linda)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'Rosa.Lima@Example.com', 'name' => 'Rosa Lima', 'role' => 'agent', 'locale' => 'es'])
            ->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'Invite sent.');

        $invitation = invitationFor('rosa.lima@example.com');
        expect($invitation->role)->toBe(Role::Agent)
            ->and($invitation->locale)->toBe('es')
            ->and($invitation->expires_at->diffInDays(now()->addDays(14)) < 1)->toBeTrue()
            ->and($invitation->secret['code'])->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/')
            ->and($invitation->token_hash)->toBe(hash('sha256', $invitation->secret['token']));

        Notification::assertSentOnDemand(InvitationSent::class, function (InvitationSent $sent, array $channels, AnonymousNotifiable $to) use ($invitation) {
            $mail = $sent->toMail($to);

            return $to->routes['mail'] === 'rosa.lima@example.com'
                && $sent->link === LS.'/invite/'.$invitation->secret['token']
                && str_contains((string) $mail->subject, 'Linda Park')
                && $sent->locale === 'es';
        });
        expect(AuditEvent::query()->where('action', 'invite.sent')->sole())
            ->organisation_id->toBe($lone->id)
            ->properties->toMatchArray(['count' => 1, 'role' => 'agent']);
    });

    it('invites many from pasted lines and a CSV, skipping duplicates, members and lines that aren’t contacts', function () {
        $lone = inviteBrokerage();
        $linda = inviteBroker($lone);
        User::factory()->withRole(Role::Agent, $lone)->create(['email' => 'james@lonestar.example']);
        $csv = UploadedFile::fake()->createWithContent('agents.csv', "name,email,phone\nTom Nguyen,tom@example.com,\nAna Ruiz,,(832) 555-0199\n");

        $this->actingAs($linda)->post('/organisation/invites', [
            'mode' => 'many',
            'people' => "Rosa Lima, rosa@example.com\nrosa@example.com\njames@lonestar.example\nnot a contact",
            'file' => $csv,
            'role' => 'agent',
            'locale' => 'en',
        ])->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', '3 invites sent.')
            ->assertInertiaFlash('inviteResult.sent', 3)
            ->assertInertiaFlash('inviteResult.skipped.0.reason', 'duplicate')
            ->assertInertiaFlash('inviteResult.skipped.1.reason', 'member')
            ->assertInertiaFlash('inviteResult.skipped.2', ['contact' => 'not a contact', 'reason' => 'invalid']);

        expect(invitationFor('rosa@example.com')->name)->toBe('Rosa Lima')
            ->and(invitationFor('+18325550199')->name)->toBe('Ana Ruiz');
        // The text went out through the Twilio SMS simulator.
        expect(app(SimulatorTextMessages::class)->outbox()[0])
            ->to->toBe('+18325550199')
            ->body->toContain('Lone Star Realty')->toContain('/invite/');
    });

    it('stops at the seat limit, all or nothing, and asks the owner for more', function () {
        $lone = inviteBrokerage(seats: 3);
        $linda = inviteBroker($lone);
        User::factory()->count(2)->withRole(Role::Agent, $lone)->create();

        $this->actingAs($linda)->post('/organisation/invites', ['mode' => 'many', 'people' => "a@example.com\nb@example.com", 'role' => 'agent', 'locale' => 'en'])
            ->assertSessionHasErrors(['seats' => 'Only 1 seat is left of 3, and this invites 2. Invite fewer people or ask for more seats.']);
        expect(Invitation::query()->count())->toBe(0);

        // Brokers don't take a seat; one agent fits, then the pending invite holds the last seat.
        $this->actingAs($linda)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'office@example.com', 'role' => 'broker', 'locale' => 'en'])->assertSessionHasNoErrors();
        $this->actingAs($linda)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'a@example.com', 'role' => 'agent', 'locale' => 'en'])->assertSessionHasNoErrors();
        $this->actingAs($linda)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'b@example.com', 'role' => 'agent', 'locale' => 'en'])->assertSessionHasErrors('seats');

        // The owner asking goes to REOS; once a day.
        $ops = User::factory()->superAdmin()->create();
        $this->actingAs($linda)->post('/organisation/seats/request', ['note' => 'Twelve agents joining in January'])->assertInertiaFlash('toast.type', 'success');
        $this->actingAs($linda)->post('/organisation/seats/request')->assertInertiaFlash('toast.type', 'info');
        Notification::assertSentTo($ops, SeatsRequested::class, fn (SeatsRequested $n) => $n->note === 'Twelve agents joining in January' && $n->limit === 3);
        expect(AuditEvent::query()->where('action', 'seats.requested')->sole()->properties)->toMatchArray(['to' => 'reos', 'used' => 2, 'pending' => 1]);
    });

    it('lets a team leader invite agents into their own team only', function () {
        $lone = inviteBrokerage();
        $okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $lone->id]);
        $other = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'North Team', 'parent_id' => $lone->id]);
        $marcus = User::factory()->withRole(Role::Broker, $okoye)->withTwoFactor()->create();

        $this->actingAs($marcus)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'x@example.com', 'role' => 'broker', 'locale' => 'en'])->assertSessionHasErrors('role');
        $this->actingAs($marcus)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'x@example.com', 'role' => 'agent', 'team_id' => $other->id, 'locale' => 'en'])->assertSessionHasErrors('team_id');
        $this->actingAs($marcus)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'x@example.com', 'role' => 'agent', 'locale' => 'en'])->assertSessionHasNoErrors();

        expect(invitationFor('x@example.com'))->organisation_id->toBe($lone->id)->team_id->toBe($okoye->id);
    });

    it('resends and withdraws, audited, only within the tenant', function () {
        $lone = inviteBrokerage();
        $linda = inviteBroker($lone);
        $this->actingAs($linda)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'rosa@example.com', 'role' => 'agent', 'locale' => 'en']);
        $invitation = invitationFor('rosa@example.com');

        $elsewhere = inviteBroker(Organisation::factory()->create(), 'broker@elsewhere.example');
        $this->actingAs($elsewhere)->post("/organisation/invites/{$invitation->id}/resend")->assertNotFound();
        $this->actingAs($elsewhere)->delete("/organisation/invites/{$invitation->id}")->assertNotFound();

        $this->actingAs($linda)->post("/organisation/invites/{$invitation->id}/resend")->assertRedirect();
        $this->actingAs($linda)->delete("/organisation/invites/{$invitation->id}")->assertRedirect();

        expect(invitationFor('rosa@example.com')->status)->toBe(Invitation::REVOKED)
            ->and(AuditEvent::query()->whereIn('action', ['invite.resent', 'invite.revoked'])->pluck('organisation_id')->unique()->all())->toBe([$lone->id]);
        Notification::assertSentOnDemandTimes(InvitationSent::class, 2);
    });
});

describe('accepting', function () {
    it('creates the account for a new person, verified, gives the role and signs them in; the link works once', function () {
        $lone = inviteBrokerage();
        $this->actingAs(inviteBroker($lone))->post('/organisation/invites', ['mode' => 'one', 'contact' => 'rosa@example.com', 'name' => 'Rosa Lima', 'role' => 'agent', 'locale' => 'en']);
        $link = inviteLink(invitationFor('rosa@example.com'));
        auth()->logout();

        $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/invite')
            ->where('state', 'open')
            ->where('account', 'new')
            ->where('invite.tenant', 'Lone Star Realty')
            ->where('invite.inviter', 'Linda Park')
            ->where('invite.contact', 'rosa@example.com'));

        $this->post($link.'/register', ['name' => 'Rosa Lima', 'password' => 'a-Long-passphrase-42'])->assertRedirect('/dashboard');

        $rosa = User::query()->where('email', 'rosa@example.com')->sole();
        $this->assertAuthenticatedAs($rosa);
        expect($rosa->email_verified_at)->not->toBeNull()
            ->and(app(Memberships::class)->find($rosa, Role::Agent, $lone->id))->not->toBeNull()
            ->and(invitationFor('rosa@example.com')->status)->toBe(Invitation::ACCEPTED)
            ->and(AuditEvent::query()->where('action', 'invite.accepted')->sole()->organisation_id)->toBe($lone->id);

        auth()->logout();
        $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'accepted'));
        $this->post($link.'/register', ['name' => 'Someone', 'password' => 'a-Long-passphrase-42'])->assertSessionHasErrors('invite');
    });

    it('adds a second tenant to someone who already has an account, and switches them into it', function () {
        $lone = inviteBrokerage();
        $texas = Organisation::factory()->ofType(OrganisationType::Association)->create(['name' => 'Texas REALTORS®']);
        $karen = User::factory()->withRole(Role::AssociationAdmin, $texas)->withTwoFactor()->create(['email' => 'karen@texasrealtors.example']);
        $this->actingAs(inviteBroker($lone))->post('/organisation/invites', ['mode' => 'one', 'contact' => 'KAREN@texasrealtors.example', 'role' => 'mentor', 'locale' => 'en']);
        $link = inviteLink(invitationFor('karen@texasrealtors.example'));

        auth()->logout();
        $this->get($link)->assertInertia(fn (Assert $page) => $page->where('account', 'existing'));
        $this->get($link.'/sign-in')->assertRedirect('/login');

        $this->actingAs($karen)->get($link)->assertInertia(fn (Assert $page) => $page->where('account', 'match'));
        $this->actingAs($karen)->post($link.'/accept')->assertRedirect('/dashboard')->assertInertiaFlash('toast.message', 'Welcome to Lone Star Realty.');

        $roles = collect(app(Memberships::class)->of($karen))->map(fn ($m) => $m->role->value.'@'.$m->organisation->name)->all();
        expect($roles)->toContain('association_admin@Texas REALTORS®')->toContain('mentor@Lone Star Realty');
    });

    it('refuses someone signed in with another account', function () {
        $lone = inviteBrokerage();
        $this->actingAs(inviteBroker($lone))->post('/organisation/invites', ['mode' => 'one', 'contact' => 'rosa@example.com', 'role' => 'agent', 'locale' => 'en']);
        $link = inviteLink(invitationFor('rosa@example.com'));
        $pat = User::factory()->withRole(Role::Agent)->create(['email' => 'pat@example.com']);

        $this->actingAs($pat)->get($link)->assertInertia(fn (Assert $page) => $page->where('account', 'other')->where('signedInAs', 'pat@example.com'));
        $this->actingAs($pat)->post($link.'/accept')->assertSessionHasErrors(['invite' => __('invites.errors.otherPerson')]);
        expect(app(Memberships::class)->find($pat, Role::Agent, $lone->id))->toBeNull();
    });

    it('joins by text with a name only; the phone is verified', function () {
        $lone = inviteBrokerage();
        $this->actingAs(inviteBroker($lone))->post('/organisation/invites', ['mode' => 'one', 'contact' => '832-555-0123', 'role' => 'agent', 'locale' => 'en']);
        $link = inviteLink(invitationFor('+18325550123'));
        auth()->logout();

        $this->post($link.'/register', ['name' => 'Ana Ruiz', 'password' => 'x-should-not-be-here'])->assertSessionHasErrors('password');
        $this->post($link.'/register', ['name' => 'Ana Ruiz'])->assertRedirect('/dashboard');

        expect(User::query()->where('phone', '+18325550123')->sole())
            ->phone_verified_at->not->toBeNull()
            ->password->toBeNull();
    });

    it('finds the invite from its code', function () {
        $lone = inviteBrokerage();
        $this->actingAs(inviteBroker($lone))->post('/organisation/invites', ['mode' => 'one', 'contact' => 'rosa@example.com', 'role' => 'agent', 'locale' => 'en']);
        $invitation = invitationFor('rosa@example.com');
        auth()->logout();

        $this->post('/join', ['code' => strtolower(str_replace('-', ' ', $invitation->secret['code']))])->assertRedirect(inviteLink($invitation));
        $this->post('/join', ['code' => 'AAAA-AAAA'])->assertSessionHasErrors(['code' => __('invites.errors.code')]);
        $this->get(LS.'/invite/'.str_repeat('x', 48))->assertNotFound()->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    });

    it('reminds once at day 7 and expires at day 14', function () {
        $lone = inviteBrokerage();
        $this->actingAs(inviteBroker($lone))->post('/organisation/invites', ['mode' => 'one', 'contact' => 'rosa@example.com', 'role' => 'agent', 'locale' => 'en']);
        $link = inviteLink(invitationFor('rosa@example.com'));

        $this->travel(6)->days();
        Artisan::call('reos:invites:remind');
        Notification::assertSentOnDemandTimes(InvitationSent::class, 1);

        $this->travel(2)->days();
        Artisan::call('reos:invites:remind');
        Artisan::call('reos:invites:remind');
        Notification::assertSentOnDemandTimes(InvitationSent::class, 2);
        Notification::assertSentOnDemand(InvitationSent::class, fn (InvitationSent $sent) => $sent->reminder);

        $this->travel(7)->days();
        auth()->logout();
        $this->get($link)->assertInertia(fn (Assert $page) => $page->where('state', 'expired'));
        $this->post($link.'/register', ['name' => 'Rosa', 'password' => 'a-Long-passphrase-42'])->assertSessionHasErrors('invite');
    });
});

describe('Agents (screen 32)', function () {
    it('lists agents with their teams and open invites, and exports the roster safely', function () {
        $lone = inviteBrokerage();
        $linda = inviteBroker($lone);
        $okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $lone->id]);
        User::factory()->withRole(Role::Agent, $lone)->create(['name' => 'James Miller']);
        User::factory()->withRole(Role::Agent, $okoye)->create(['name' => '=HYPERLINK("x")']);
        $this->actingAs($linda)->post('/organisation/invites', ['mode' => 'one', 'contact' => 'rosa@example.com', 'role' => 'agent', 'locale' => 'en']);

        $this->actingAs($linda)->get('/agents')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('organisation/agents')
            ->has('agents', 2)
            ->where('agents.1.name', 'James Miller')
            ->where('agents.0.team', 'Okoye Team')
            ->has('invitations', 1)
            ->where('invitations.0.contact', 'rosa@example.com')
            ->where('invite.seats.used', 2)
            ->where('invite.seats.pending', 1)
            ->where('invite.teams.0.name', 'Okoye Team'));

        $csv = $this->actingAs($linda)->get('/agents/export')->assertOk()->streamedContent();
        expect($csv)->toContain('James Miller')->toContain("'=HYPERLINK");
    });

    it('is for brokers and team leaders', function () {
        $this->actingAs(User::factory()->withRole(Role::Agent)->create())->get('/agents')->assertForbidden();
        $this->actingAs(User::factory()->withRole(Role::AssociationAdmin)->withTwoFactor()->create())->get('/agents')->assertNotFound();
    });
});

describe('Ask my broker', function () {
    it('lets a brokerage’s agent ask for a tool the brokerage sets up, telling the broker a minute later unless undone', function () {
        $lone = inviteBrokerage();
        $linda = inviteBroker($lone);
        $james = User::factory()->withRole(Role::Agent, $lone)->create(['name' => 'James Miller', 'onboarded_at' => now()]);

        $this->actingAs($james)->get('/connections')->assertInertia(fn (Assert $page) => $page
            ->where('tools', fn ($tools) => collect($tools)->contains(fn ($t) => $t['id'] === 'lone-wolf' && $t['action'] === 'ask_broker' && $t['accounts'] === 'tenant')));

        $this->actingAs($james)->from('/connections')->post('/connections/tools/lone-wolf/interest')->assertRedirect('/connections');
        $this->actingAs($james)->from('/connections')->post('/connections/tools/lone-wolf/interest');

        Notification::assertSentToTimes($linda, BrokerAsked::class, 1);
        Notification::assertSentTo($linda, BrokerAsked::class, fn (BrokerAsked $n) => $n->tool === 'Lone Wolf Transactions' && $n->delay !== null && $n->shouldSend($linda, 'mail'));
        expect(AuditEvent::query()->where('action', 'connection.broker_asked')->sole()->organisation_id)->toBe($lone->id)
            ->and(IntegrationInterest::query()->where('kind', IntegrationInterest::BROKER)->count())->toBe(1);

        // Undo: the mail no longer goes.
        $this->actingAs($james)->from('/connections')->delete('/connections/tools/lone-wolf/interest');
        Notification::assertSentTo($linda, BrokerAsked::class, fn (BrokerAsked $n) => ! $n->shouldSend($linda, 'mail'));
    });

    it('shows Notify me to agents without a brokerage', function () {
        $independent = User::factory()->withRole(Role::Agent, Organisation::platform())->create(['onboarded_at' => now()]);

        $this->actingAs($independent)->get('/connections')->assertInertia(fn (Assert $page) => $page
            ->where('tools', fn ($tools) => collect($tools)->every(fn ($t) => $t['action'] !== 'ask_broker')));
    });
});

it('keeps the parsing honest', function () {
    $parsed = app(Invitations::class)->parse("Email,Name\nrosa@example.com,Rosa Lima\n(832) 555-0123\n555\n\n+44 20 7946 0958; Sam Hill");

    expect($parsed['people'])->toBe([
        ['email' => 'rosa@example.com', 'phone' => null, 'name' => 'Rosa Lima'],
        ['email' => null, 'phone' => '+18325550123', 'name' => null],
        ['email' => null, 'phone' => '+442079460958', 'name' => 'Sam Hill'],
    ])->and($parsed['invalid'])->toBe(['555']);

    Cache::flush();
});
