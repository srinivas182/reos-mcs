<?php

declare(strict_types=1);

use App\Compliance\ComplianceEngine;
use App\Models\ComplianceEvent;
use App\Models\Contact;
use App\Models\FarmSend;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\FarmUpdateMail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Farming market updates (B14.3c, ADR 0068): MLS figures for an area,
 * checked by the compliance engine, to the contacts tagged for it.
 */

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->artisan('reos:mls:sync --now')->assertSuccessful();
    $this->james = mlsAgent('James Miller', '0798765');
    $this->actingAs($this->james)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
});

function farmContact(User $agent, string $name, ?string $email, string $tag = 'Katy farm'): Contact
{
    $contact = Contact::query()->create(['uuid' => (string) Str::uuid7(), 'owner_type' => 'user', 'owner_id' => $agent->id, 'first_name' => $name, 'emails' => $email === null ? [] : [['value' => $email, 'source' => 'reos']], 'phones' => [], 'field_times' => []]);
    $contact->tags()->attach(Tag::query()->firstOrCreate(['owner_type' => 'user', 'owner_id' => $agent->id, 'name' => $tag])->id);

    return $contact;
}

function katy(object $test): array
{
    return $test->postJson('/api/v1/farm-areas', ['name' => 'Katy', 'city' => 'Katy', 'tag' => 'katy farm', 'mailing_address' => '1200 Grand Pkwy, Katy, TX 77494'])->assertOk()->json('data');
}

describe('farming market updates (B14.3c)', function () {
    it('drafts an update from MLS figures, without sale prices in a non-disclosure state, and passes the compliance check', function () {
        farmContact($this->james, 'Ana', 'ana@example.com');
        farmContact($this->james, 'Ravi', null);
        $area = katy($this);
        expect($area['recipients'])->toBe(1);

        $draft = $this->getJson("/api/v1/farm-areas/{$area['id']}/draft")->assertOk()->json('data');
        expect($draft['stats']['active'])->toBeGreaterThan(0)->and($draft['stats']['sold'])->toBeGreaterThan(0)
            // Texas MLS rules: sale prices stay off public material.
            ->and($draft['stats']['median_sold_price'])->toBeNull()
            ->and($draft['subject'])->toBe('Katy market update, November 2026')
            ->and($draft['body'])->not->toContain('median price of $')->toContain('homes sold.')
            ->and($draft['body'])->toContain((string) app(ComplianceEngine::class)->brokerName($this->james))
            ->and($draft['attribution'])->toStartWith('Based on information from HAR')
            ->and($draft['check']['ok'])->toBeTrue()->and($draft['can_send'])->toBeTrue();
    });

    it('sends to tagged contacts with one-click unsubscribe, logs the check, and waits a week', function () {
        farmContact($this->james, 'Ana', 'ana@example.com');
        farmContact($this->james, 'Ben', 'ben@example.com');
        farmContact($this->james, 'Cy', 'cy@example.com', 'Sugar Land');
        $area = katy($this);
        $draft = $this->getJson("/api/v1/farm-areas/{$area['id']}/draft")->json('data');

        $this->postJson("/api/v1/farm-areas/{$area['id']}/send", ['subject' => $draft['subject'], 'body' => $draft['body']])->assertOk()->assertJsonPath('data.can_send', false);
        expect(FarmSend::query()->sole()->recipients)->toBe(2)
            ->and(ComplianceEvent::query()->where('channel', 'newsletter')->where('action', 'checked')->count())->toBe(1);

        $unsubscribe = null;
        Notification::assertSentTo(new AnonymousNotifiable, FarmUpdateMail::class, function (FarmUpdateMail $n, array $channels, AnonymousNotifiable $to) use (&$unsubscribe) {
            if ($to->routes['mail'] !== 'ana@example.com') {
                return false;
            }
            $mail = $n->toMail($to);
            $unsubscribe = $n->unsubscribeUrl();

            return $mail->subject === 'Katy market update, November 2026' && in_array('Sent by James Miller, '.app(ComplianceEngine::class)->brokerName($this->james).', 1200 Grand Pkwy, Katy, TX 77494.', $mail->introLines, true)
                && ! str_contains($unsubscribe, 'ana@example.com');
        });
        Notification::assertSentTimes(FarmUpdateMail::class, 2);
        $this->postJson("/api/v1/farm-areas/{$area['id']}/send", ['subject' => $draft['subject'], 'body' => $draft['body']])->assertUnprocessable()->assertJsonValidationErrors('area');

        // Ana unsubscribes: a person sees a button first; a mail app's one-click POST works without a session.
        auth()->forgetGuards();
        $this->get((string) $unsubscribe)->assertOk()->assertSee('Stop getting market updates from James Miller?');
        $this->post((string) $unsubscribe, ['List-Unsubscribe' => 'One-Click'])->assertNoContent();
        $this->get(str_replace('signature=', 'signature=x', (string) $unsubscribe))->assertForbidden();

        $this->travel(8)->days();
        Notification::fake();
        $this->actingAs($this->james)->postJson("/api/v1/farm-areas/{$area['id']}/send", ['subject' => $draft['subject'], 'body' => $draft['body']])->assertOk();
        Notification::assertSentTimes(FarmUpdateMail::class, 1);
        Notification::assertSentTo(new AnonymousNotifiable, FarmUpdateMail::class, fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'ben@example.com');
    });

    it('blocks an update the compliance engine blocks', function () {
        farmContact($this->james, 'Ana', 'ana@example.com');
        $area = katy($this);

        $this->postJson("/api/v1/farm-areas/{$area['id']}/send", ['subject' => 'Katy update', 'body' => 'Katy is perfect for families, and we guarantee your home will sell. James Miller'])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        expect(FarmSend::query()->count())->toBe(0)->and(ComplianceEvent::query()->where('channel', 'newsletter')->where('action', 'blocked')->count())->toBe(1);
        Notification::assertNothingSent();
    });

    it('keeps areas to their agent, needs an MLS, and shows on the web', function () {
        $area = katy($this);
        $other = mlsAgent('Dev Shah', '0711111');
        $this->actingAs($other)->getJson("/api/v1/farm-areas/{$area['id']}/draft")->assertNotFound();
        $mine = $this->postJson('/api/v1/farm-areas', ['name' => 'Austin', 'postal_code' => '78704', 'tag' => 'austin', 'mailing_address' => '500 Congress Ave, Austin, TX 78701'])->json('data');
        $this->getJson("/api/v1/farm-areas/{$mine['id']}/draft")->assertUnprocessable()->assertJsonValidationErrors('area');
        $this->postJson('/api/v1/farm-areas', ['name' => 'Nowhere', 'tag' => 'x', 'mailing_address' => 'short'])->assertJsonValidationErrors(['city', 'postal_code', 'mailing_address']);

        $this->actingAs($this->james)->get('/farming')->assertOk()->assertInertia(fn (Assert $page) => $page->component('farming/index')->has('areas', 1));
        $this->deleteJson("/api/v1/farm-areas/{$area['id']}")->assertNoContent();
        $this->getJson('/api/v1/farm-areas')->assertJsonPath('data', []);
    });
});
