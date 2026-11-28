<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Points;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Leads\Invites;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\Referral;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\InviteRewarded;
use App\Notifications\ReferralAnswered;
use App\Notifications\ReferralEmail;
use App\Notifications\ReferralReceived;
use App\Services\Licences\Licences;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Referrals between agents and invite and earn (B14.3b, ADR 0067).
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00', 'America/Chicago'));
    $this->maria = User::factory()->withRole(Role::Agent, Organisation::factory()->create(['name' => 'Lone Star Realty']))->create(['name' => 'Maria Garcia', 'email' => 'maria@example.com']);
    $this->dev = User::factory()->withRole(Role::Agent, Organisation::factory()->create(['name' => 'Hill Country Homes']))->create(['name' => 'Dev Shah', 'email' => 'dev.shah@example.com']);
});

function refer(object $test, array $over = []): array
{
    return $test->actingAs($test->maria)->postJson('/api/v1/referrals', [
        'to_name' => 'Dev', 'to_email' => 'Dev.Shah@example.com', 'client_name' => 'Elena Torres', 'client_email' => 'elena@example.com',
        'kind' => 'buyer', 'area' => 'Austin', 'note' => 'Relocating in March; two kids.', 'fee_percent' => 25, 'client_agreed' => true, ...$over,
    ])->assertOk()->json('data');
}

describe('referrals between agents (B14.3b)', function () {
    it('sends a client to an agent at another brokerage, who accepts into their CRM', function () {
        $sent = refer($this);
        expect($sent)->toMatchArray(['direction' => 'sent', 'status' => 'sent', 'kind' => 'buyer', 'fee_percent' => 25])
            ->and($sent['from'])->toBe(['name' => 'Maria Garcia', 'broker' => 'Lone Star Realty'])
            ->and($sent['to'])->toBe(['name' => 'Dev Shah', 'email' => 'dev.shah@example.com', 'broker' => 'Hill Country Homes', 'on_reos' => true]);
        Notification::assertSentTo($this->dev, ReferralReceived::class, fn (ReferralReceived $n) => $n->inbox($this->dev)['title'] === 'Maria Garcia referred a client to you');

        // Across brokerages: Dev sees it; a third agent doesn't.
        $other = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($other)->postJson("/api/v1/referrals/{$sent['id']}/accept")->assertNotFound();
        $this->actingAs($this->maria)->postJson("/api/v1/referrals/{$sent['id']}/accept")->assertNotFound();
        $this->actingAs($this->dev)->getJson('/api/v1/referrals')->assertJsonPath('data.received.0.client.name', 'Elena Torres')->assertJsonPath('data.sent', []);

        $accepted = $this->postJson("/api/v1/referrals/{$sent['id']}/accept")->assertOk()->json('data');
        $contact = Contact::query()->where('owner_id', $this->dev->id)->sole();
        expect($accepted['status'])->toBe('accepted')->and($accepted['contact'])->toBe($contact->uuid)
            ->and(ContactNote::query()->where('contact_id', $contact->id)->pluck('body')->first())->toBe('Referred by Maria Garcia: Buyer in Austin. Referral fee 25%, paid broker to broker at closing. Their note: “Relocating in March; two kids.”')
            ->and(Todo::query()->where('user_id', $this->dev->id)->sole()->title)->toBe('Follow up with Elena');
        Notification::assertSentTo($this->maria, ReferralAnswered::class, fn (ReferralAnswered $n) => $n->accepted);
        $this->postJson("/api/v1/referrals/{$sent['id']}/decline")->assertUnprocessable();

        // Either agent marks it closed.
        $this->actingAs($this->maria)->postJson("/api/v1/referrals/{$sent['id']}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    });

    it('emails an agent outside REOS, never refers to yourself, and needs the client\'s agreement', function () {
        $sent = refer($this, ['to_name' => 'Pat Kim', 'to_email' => 'pat@coastal.example', 'to_brokerage' => 'Coastal Realty', 'client_email' => null, 'client_phone' => '512 555 0101']);
        expect($sent['status'])->toBe('emailed')->and($sent['to'])->toMatchArray(['on_reos' => false, 'broker' => 'Coastal Realty']);
        Notification::assertSentTo(new AnonymousNotifiable, ReferralEmail::class, function (ReferralEmail $n, array $channels, AnonymousNotifiable $to) {
            $mail = $n->toMail($to);

            return $to->routes['mail'] === 'pat@coastal.example' && $mail->subject === 'Maria Garcia would like to refer a client to you' && $mail->replyTo === [['maria@example.com', 'Maria Garcia']]
                && in_array('Proposed referral fee: 25%, paid broker to broker at closing, as your brokers agree.', $mail->introLines, true);
        });

        $this->postJson('/api/v1/referrals', ['to_name' => 'Me', 'to_email' => 'maria@example.com', 'client_name' => 'Elena Torres', 'client_email' => 'e@example.com', 'kind' => 'buyer', 'client_agreed' => true])->assertUnprocessable()->assertJsonValidationErrors('to_email');
        $this->postJson('/api/v1/referrals', ['to_name' => 'Dev', 'to_email' => 'dev.shah@example.com', 'client_name' => 'Elena Torres', 'client_email' => 'e@example.com', 'kind' => 'buyer'])->assertJsonValidationErrors('client_agreed');
        $this->postJson('/api/v1/referrals', ['to_name' => 'Dev', 'to_email' => 'dev.shah@example.com', 'client_name' => 'Elena Torres', 'kind' => 'buyer', 'client_agreed' => true, 'fee_percent' => 80])->assertJsonValidationErrors(['client_email', 'fee_percent']);
        expect(Referral::query()->count())->toBe(1);
    });

    it('shows referrals and the invite link on the web', function () {
        refer($this);
        $this->get('/referrals')->assertOk()->assertInertia(fn (Assert $page) => $page->component('referrals/index')->has('referrals.sent', 1)->where('invite.points_each', 2500));
    });
});

describe('invite and earn (B14.3b)', function () {
    it('gives the inviter points once, when the person they invited gets licensed', function () {
        $code = $this->actingAs($this->maria)->getJson('/api/v1/me/invite')->assertOk()->json('data.code');
        expect($code)->toMatch('/^[A-HJ-NP-Z2-9]{8}$/');
        auth()->forgetGuards();

        $this->get("/join/{$code}")->assertOk()->assertSee('Maria invited you to REOS')->assertSee($code);
        $this->post('/register', ['name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'password' => 'a-Strong-passw0rd-2026', 'password_confirmation' => 'a-Strong-passw0rd-2026', 'terms' => '1'])->assertRedirect();
        $ana = User::query()->where('email', 'ana@example.com')->sole();
        expect($ana->invited_by_id)->toBe($this->maria->id);

        $licence = Licence::query()->create(['user_id' => $ana->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0812345', 'status' => LicenceStatus::PendingReview, 'holder_name' => 'Ana Ruiz']);
        $licences = app(Licences::class);
        $licences->approve($licence, User::factory()->create());
        // Approved again (or verified twice): still one award.
        $licences->approve($licence->refresh(), User::factory()->create());

        expect(app(Points::class)->balance($this->maria))->toBe(2500);
        Notification::assertSentToTimes($this->maria, InviteRewarded::class, 1);
        Notification::assertSentTo($this->maria, InviteRewarded::class, fn (InviteRewarded $n) => $n->inbox($this->maria)['title'] === 'Ana R. got their license: you earned 2,500 points');
        $this->actingAs($this->maria)->getJson('/api/v1/me/invite')->assertJsonPath('data.earned', 2500)->assertJsonPath('data.invited.0', ['name' => 'Ana R.', 'joined_at' => now()->toIso8601String(), 'licensed' => true]);
    });

    it('lets someone who signed up in the app enter the code, once and not too late', function () {
        $code = app(Invites::class)->summary($this->maria)['code'];
        $sam = User::factory()->create(['name' => 'Sam Lee']);

        $this->actingAs($sam)->postJson('/api/v1/me/invite/claim', ['code' => $sam->invite_code ?? 'ZZZZZZZZ'])->assertUnprocessable();
        $this->postJson('/api/v1/me/invite/claim', ['code' => strtolower($code)])->assertOk()->assertJsonPath('data.invited_by', 'Maria G.')->assertJsonPath('data.can_claim', false);
        $this->postJson('/api/v1/me/invite/claim', ['code' => $code])->assertUnprocessable();

        // Their own code doesn't count; nor does a code entered after 30 days or once licensed.
        $own = app(Invites::class)->summary($this->dev)['code'];
        $this->actingAs($this->dev)->postJson('/api/v1/me/invite/claim', ['code' => $own])->assertUnprocessable();
        $old = User::factory()->create(['created_at' => now()->subDays(31)]);
        $this->actingAs($old)->postJson('/api/v1/me/invite/claim', ['code' => $code])->assertUnprocessable();
        expect(User::query()->where('invited_by_id', $this->maria->id)->count())->toBe(1);
    });
});
