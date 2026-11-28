<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\DocuSign\DocuSignSimulator;
use App\Deals\ClientPortals;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\AgentPage;
use App\Models\ClientPortal;
use App\Models\ClientReview;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Notifications\ClientPortalLink;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The client portal (B14.2, ADR 0065): one private link per client; the
 * deal's status and dates, signing inside the portal, and a review after
 * closing that shows on the agent's page with the client's say-so.
 */

beforeEach(function () {
    Storage::fake(config()->string('reos.content.disk'));
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'email' => 'james.miller@demo.reos.test']);
    foreach (['dotloop', 'docusign'] as $tool) {
        IntegrationSetting::query()->firstOrCreate(['integration_id' => $tool], ['mode' => IntegrationMode::Simulator]);
    }
    connectOnWeb($this, $this->james, tool: 'dotloop');
    connectOnWeb($this, $this->james, tool: 'docusign');
    $this->deal = Deal::query()->where('property', '1302 Live Oak St')->sole();
    DealDate::query()->create(['deal_id' => $this->deal->id, 'kind' => 'option_period', 'due_on' => '2026-11-30']);
    DealDate::query()->create(['deal_id' => $this->deal->id, 'kind' => 'closing', 'due_on' => '2026-12-18']);
    (new AgentPage)->forceFill(['user_id' => $this->james->id, 'handle' => 'james-miller', 'published' => true, 'phone' => '(832) 555-0142'])->save();
});

/** The token is only in the link the client got. */
function portalToken(string $url): string
{
    return (string) preg_replace('#^.*/portal/#', '', $url);
}

describe('the client portal (B14.2)', function () {
    it('lets the client sign and review from one link (the done-when)', function () {
        $portal = $this->actingAs($this->james)->postJson("/api/v1/deals/{$this->deal->uuid}/portals", ['name' => 'Priya Shah', 'email' => 'Priya.Shah@example.com', 'language' => 'en'])->assertOk()->json('data');
        expect($portal)->toMatchArray(['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'opened_at' => null, 'review' => null])
            ->and($portal['url'])->toMatch('#/portal/[A-Za-z0-9]{48}$#');
        // The same client again: the same link.
        $this->postJson("/api/v1/deals/{$this->deal->uuid}/portals", ['name' => 'Priya Shah', 'email' => 'priya.shah@example.com'])->assertJsonPath('data.id', $portal['id']);

        $this->postJson("/api/v1/portals/{$portal['id']}/send")->assertOk();
        Notification::assertSentTo(new AnonymousNotifiable, ClientPortalLink::class, fn (ClientPortalLink $n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'priya.shah@example.com' && $n->link === $portal['url']);

        // James sends the disclosure; Priya, who has a link, signs inside her portal.
        $file = $this->postJson("/api/v1/deals/{$this->deal->uuid}/files", ['file' => UploadedFile::fake()->createWithContent('Seller\'s Disclosure.pdf', DocuSignSimulator::pdf('Seller\'s Disclosure Notice'))])->assertCreated()->json('data.uuid');
        $sender = (string) $this->postJson("/api/v1/deals/{$this->deal->uuid}/signatures", ['subject' => 'Seller\'s Disclosure', 'files' => [$file], 'signers' => [['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'routing_order' => 1]]])->assertCreated()->json('sender_url');
        $request = SignatureRequest::query()->sole();
        expect($request->signers[0]['client_user_id'])->toBe('portal-'.$portal['id']);
        $this->get((string) $this->post($sender)->headers->get('Location'));
        expect($request->refresh()->status)->toBe('sent');
        auth()->forgetGuards();

        $token = portalToken($portal['url']);
        $this->get("/portal/{$token}")->assertOk()
            ->assertSee('1302 Live Oak St')->assertSee('Option period ends')->assertSee('Closing')->assertSee('Ready for your signature.')
            ->assertSee('James Miller')->assertSee('Lone Star Realty')->assertSee('tel:8325550142', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('How did James Miller do?');
        expect(ClientPortal::query()->sole()->opened_at)->not->toBeNull();

        // Sign: DocuSign's signing view (the demo), then back to the portal, signed.
        $signing = $this->post("/portal/{$token}/sign/{$request->uuid}")->assertRedirect()->headers->get('Location');
        expect($signing)->toContain('/portal-simulator/sign');
        $this->get((string) $signing)->assertOk()->assertSee('Sign and finish');
        preg_match('#action="([^"]+)"#', (string) $this->get((string) $signing)->getContent(), $form);
        $back = $this->post(html_entity_decode($form[1]))->assertRedirect()->headers->get('Location');
        expect($back)->toBe(route('portal.show', ['token' => $token, 'signed' => $request->uuid]));
        $this->get((string) $back)->assertOk();
        $this->get((string) $back)->assertOk()->assertSee('You signed this.');
        expect($request->refresh()->status)->toBe('completed');

        // After closing: the review, shown on James's page with Priya's say-so.
        $this->deal->forceFill(['stage' => 'closed', 'closed_at' => now()])->save();
        $this->get("/portal/{$token}")->assertSee('How did James Miller do?');
        $this->post("/portal/{$token}/review", ['rating' => 5, 'comment' => 'Calm, clear and always one step ahead.', 'publish' => '1'])->assertRedirect("/portal/{$token}");
        $this->get("/portal/{$token}")->assertSee('Thank you. Your review was saved.')->assertSee('Update my review');
        expect(ClientReview::query()->sole())->rating->toBe(5)->shown_name->toBe('Priya S.')->publish->toBeTrue();

        $this->get('/agent/james-miller')->assertOk()->assertSee('What clients say')->assertSee('5.0 out of 5 from 1 clients')->assertSee('Calm, clear and always one step ahead.')->assertSee('Priya S., 5 of 5');
        $this->actingAs($this->james)->getJson("/api/v1/deals/{$this->deal->uuid}/portals")->assertJsonPath('data.0.review', ['rating' => 5, 'publish' => true]);
    });

    it('keeps a review private unless the client allows it, and only after closing', function () {
        $url = $this->actingAs($this->james)->postJson("/api/v1/deals/{$this->deal->uuid}/portals", ['name' => 'Ravi Patel', 'email' => 'ravi@example.com'])->json('data.url');
        auth()->forgetGuards();
        $token = portalToken($url);

        $this->from("/portal/{$token}")->post("/portal/{$token}/review", ['rating' => 4])->assertSessionHasErrors('rating');
        $this->deal->forceFill(['stage' => 'closed'])->save();
        $this->post("/portal/{$token}/review", ['rating' => 4, 'comment' => 'Good.'])->assertRedirect();
        $this->get('/agent/james-miller')->assertDontSee('What clients say');
    });

    it('turns links off, lets them expire, and shows nothing else', function () {
        $portal = $this->actingAs($this->james)->postJson("/api/v1/deals/{$this->deal->uuid}/portals", ['name' => 'Priya Shah', 'email' => 'priya@example.com', 'language' => 'es'])->json('data');
        $token = portalToken($portal['url']);

        // Another agent can't see or turn off James's links.
        $maria = User::factory()->withRole(Role::Agent, $this->lone)->create();
        $this->actingAs($maria)->getJson("/api/v1/deals/{$this->deal->uuid}/portals")->assertNotFound();
        $this->deleteJson("/api/v1/portals/{$portal['id']}")->assertNotFound();
        auth()->forgetGuards();

        // In the client's language.
        $this->get("/portal/{$token}")->assertOk()->assertSee('<html lang="es">', false)->assertSee('Fechas clave');
        $this->get('/portal/'.str_repeat('a', 48))->assertNotFound();

        $this->actingAs($this->james)->deleteJson("/api/v1/portals/{$portal['id']}")->assertNoContent();
        auth()->forgetGuards();
        $this->get("/portal/{$token}")->assertNotFound();

        $again = $this->actingAs($this->james)->postJson("/api/v1/deals/{$this->deal->uuid}/portals", ['name' => 'Priya Shah', 'email' => 'priya@example.com'])->json('data');
        expect($again['id'])->not->toBe($portal['id']);
        auth()->forgetGuards();
        $this->travel(ClientPortals::DAYS + 1)->days();
        $this->get('/portal/'.portalToken($again['url']))->assertNotFound();

        // No email: it can't be emailed.
        $this->actingAs($this->james)->travelBack();
        $plain = $this->postJson("/api/v1/deals/{$this->deal->uuid}/portals", ['name' => 'Walk-in buyer'])->json('data');
        $this->postJson("/api/v1/portals/{$plain['id']}/send")->assertUnprocessable()->assertJsonValidationErrors('email');
    });

    it('turns away signing when it is not the client\'s turn', function () {
        $portal = $this->actingAs($this->james)->postJson("/api/v1/deals/{$this->deal->uuid}/portals", ['name' => 'Priya Shah', 'email' => 'priya@example.com'])->json('data');
        $file = $this->postJson("/api/v1/deals/{$this->deal->uuid}/files", ['file' => UploadedFile::fake()->createWithContent('Addendum.pdf', DocuSignSimulator::pdf('Addendum'))])->json('data.uuid');
        $this->postJson("/api/v1/deals/{$this->deal->uuid}/signatures", ['subject' => 'Repair amendment to sign', 'files' => [$file], 'signers' => [['name' => 'Seller One', 'email' => 'seller@example.com', 'routing_order' => 1], ['name' => 'Priya Shah', 'email' => 'priya@example.com', 'routing_order' => 2]]])->assertCreated();
        $request = SignatureRequest::query()->sole();
        auth()->forgetGuards();
        $token = portalToken($portal['url']);

        // Still a draft: not shown; then the seller signs first.
        $this->get("/portal/{$token}")->assertDontSee('Repair amendment to sign');
        DocuSignSimulator::send($request->connection, (string) $request->envelope_id);
        $request->forceFill(['status' => 'sent', 'signers' => [[...$request->signers[0], 'status' => 'sent'], $request->signers[1]]])->save();
        $this->get("/portal/{$token}")->assertSee('Repair amendment to sign')->assertSee('Waiting for someone else to sign first.');
        $this->from("/portal/{$token}")->post("/portal/{$token}/sign/{$request->uuid}")->assertSessionHasErrors('request');
    });
});
