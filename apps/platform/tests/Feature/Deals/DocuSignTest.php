<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\DocuSign\DocuSignDriver;
use App\Connections\Drivers\DocuSign\DocuSignSimulator;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\DealFile;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Notifications\SignatureCompleted;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config()->string('reos.content.disk'));
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'email' => 'james.miller@demo.reos.test']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Maria Garcia', 'email' => 'maria.garcia@demo.reos.test']);
    foreach (['dotloop', 'docusign'] as $tool) {
        IntegrationSetting::query()->firstOrCreate(['integration_id' => $tool], ['mode' => IntegrationMode::Simulator]);
    }
});

/** James's dotloop (for the deal) and his DocuSign, both on demo data. */
function signingReady(object $test): Deal
{
    connectOnWeb($test, $test->james, tool: 'dotloop');
    connectOnWeb($test, $test->james, tool: 'docusign');

    return Deal::query()->where('property', '1302 Live Oak St')->sole();
}

function disclosurePdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('Seller\'s Disclosure.pdf', DocuSignSimulator::pdf('Seller\'s Disclosure Notice'));
}

describe('send for signature from a deal (the B9.3 done-when)', function () {
    it('prepares the envelope, lets the agent send it in DocuSign, follows the signer, and files the signed copy on the deal and in dotloop', function () {
        Notification::fake();
        $deal = signingReady($this);
        $docusign = Connection::query()->where('integration_id', 'docusign')->sole();

        $this->actingAs($this->james)->getJson("/api/v1/deals/{$deal->uuid}")->assertOk()->assertJsonPath('data.can_sign', true)->assertJsonPath('data.signatures', []);
        $file = $this->postJson("/api/v1/deals/{$deal->uuid}/files", ['file' => disclosurePdf()])->assertCreated()->assertJsonPath('data.kind', 'upload')->json('data.uuid');

        $started = $this->postJson("/api/v1/deals/{$deal->uuid}/signatures", [
            'subject' => 'Seller\'s Disclosure, 1302 Live Oak St',
            'files' => [$file],
            'signers' => [['name' => 'Priya Shah', 'email' => 'Priya.Shah@example.com', 'routing_order' => 1]],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.signers.0.email', 'priya.shah@example.com');
        $sender = (string) $started->json('sender_url');
        expect($sender)->toContain('/connections/simulator/docusign/envelopes/');

        // Nothing is sent until James presses Send in (the demo of) DocuSign's sender view.
        $this->get($sender)->assertOk()->assertInertia(fn (Assert $page) => $page->component('connections/docusign-sender')
            ->where('documents', ['Seller\'s Disclosure.pdf'])->where('signers.0.name', 'Priya Shah'));
        $request = SignatureRequest::query()->sole();
        expect($request->status)->toBe('draft');

        $back = $this->post($sender)->assertRedirect();
        expect((string) $back->headers->get('Location'))->toBe(route('deals.signatures.returned', $request->uuid).'?event=Send');
        $this->get((string) $back->headers->get('Location'))->assertRedirect('/deals?deal='.$deal->uuid)->assertSessionHas('success', 'Sent. You\'ll see each signer\'s progress here.');
        expect($request->refresh())->status->toBe('sent')->and($request->signers[0]['status'])->toBe('sent');

        // Each sync moves the demo along: Priya opens it, then signs.
        $this->postJson("/api/v1/connections/{$docusign->id}/sync")->assertAccepted();
        expect($request->refresh())->status->toBe('delivered')->and($request->signers[0]['status'])->toBe('delivered');
        $this->postJson("/api/v1/connections/{$docusign->id}/sync")->assertAccepted();

        expect($request->refresh())->status->toBe('completed')->filed_in->toBe('dotloop')
            ->and($request->filed_at)->not->toBeNull()
            ->and(DealFile::query()->where('deal_id', $deal->id)->orderBy('id')->pluck('kind')->all())->toBe(['upload', 'signed', 'certificate']);
        Notification::assertSentTo($this->james, SignatureCompleted::class, fn (SignatureCompleted $n) => $n->inbox($this->james)['title'] === 'Signed: Seller\'s Disclosure, 1302 Live Oak St, 1302 Live Oak St');

        // A later sync doesn't file it twice.
        $this->postJson("/api/v1/connections/{$docusign->id}/sync")->assertAccepted();
        expect(DealFile::query()->where('deal_id', $deal->id)->count())->toBe(3);

        // The signed copy is in dotloop too, and comes back with the loop.
        $dotloop = Connection::query()->where('integration_id', 'dotloop')->sole();
        $this->postJson("/api/v1/connections/{$dotloop->id}/sync")->assertAccepted();
        expect(collect($deal->refresh()->documents)->pluck('name')->all())->toContain('Seller\'s Disclosure, 1302 Live Oak St (signed).pdf');

        $shown = $this->getJson("/api/v1/deals/{$deal->uuid}")->assertOk()
            ->assertJsonPath('data.signatures.0.status', 'completed')
            ->assertJsonPath('data.signatures.0.signers.0.status', 'signed')
            ->assertJsonPath('data.kept_files.0.kind', 'certificate');
        $signed = collect($shown->json('data.kept_files'))->firstWhere('kind', 'signed');
        $opened = $this->get($signed['url'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($opened->baseResponse->getFile()->getContent())->toStartWith('%PDF-');
        $this->get(route('deals.files.show', $signed['uuid']))->assertForbidden();
    });

    it('keeps a draft the agent leaves, and opens DocuSign again to finish it', function () {
        $deal = signingReady($this);
        $file = $this->actingAs($this->james)->postJson("/api/v1/deals/{$deal->uuid}/files", ['file' => disclosurePdf()])->json('data.uuid');
        $sender = (string) $this->postJson("/api/v1/deals/{$deal->uuid}/signatures", ['subject' => 'Amendment', 'files' => [$file], 'signers' => [['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'routing_order' => 1]]])->json('sender_url');
        $request = SignatureRequest::query()->sole();

        $this->get(route('deals.signatures.returned', $request->uuid).'?event=Cancel')->assertRedirect('/deals?deal='.$deal->uuid)->assertSessionHas('success', 'Not sent yet. You can continue in DocuSign from the deal.');
        expect($request->refresh()->status)->toBe('draft');

        $again = (string) $this->postJson("/api/v1/deals/signatures/{$request->uuid}/sender")->assertOk()->json('sender_url');
        expect($again)->toContain('/connections/simulator/docusign/envelopes/'.$request->envelope_id);

        // Someone else's: not found. A tampered return address: refused.
        $this->actingAs($this->maria)->postJson("/api/v1/deals/signatures/{$request->uuid}/sender")->assertNotFound();
        $this->actingAs($this->maria)->get(route('deals.signatures.returned', $request->uuid))->assertNotFound();
        $this->actingAs($this->maria)->get($sender)->assertNotFound();
        $this->actingAs($this->james)->get(str_replace('returned', 'elsewhere', $sender))->assertForbidden();
    });

    it('records a declined envelope and files nothing', function () {
        $deal = signingReady($this);
        $docusign = Connection::query()->where('integration_id', 'docusign')->sole();
        $file = $this->actingAs($this->james)->postJson("/api/v1/deals/{$deal->uuid}/files", ['file' => disclosurePdf()])->json('data.uuid');
        $this->postJson("/api/v1/deals/{$deal->uuid}/signatures", ['subject' => 'Amendment', 'files' => [$file], 'signers' => [['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'routing_order' => 1]]]);
        $request = SignatureRequest::query()->sole();
        DocuSignSimulator::send($docusign, (string) $request->envelope_id);
        DocuSignSimulator::decline($docusign, (string) $request->envelope_id, 'priya.shah@example.com');

        $this->postJson("/api/v1/connections/{$docusign->id}/sync")->assertAccepted();

        expect($request->refresh())->status->toBe('declined')->and($request->signers[0]['status'])->toBe('declined')
            ->and(DealFile::query()->where('kind', 'signed')->exists())->toBeFalse();
    });

    it('asks for DocuSign first, and only takes PDFs from the agent\'s own deal', function () {
        connectOnWeb($this, $this->james, tool: 'dotloop');
        $deal = Deal::query()->where('property', '1302 Live Oak St')->sole();
        $this->actingAs($this->james)->getJson("/api/v1/deals/{$deal->uuid}")->assertJsonPath('data.can_sign', false);
        $file = $this->postJson("/api/v1/deals/{$deal->uuid}/files", ['file' => disclosurePdf()])->assertCreated()->json('data.uuid');

        $this->postJson("/api/v1/deals/{$deal->uuid}/signatures", ['subject' => 'Amendment', 'files' => [$file], 'signers' => [['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'routing_order' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('connection');
        $this->postJson("/api/v1/deals/{$deal->uuid}/files", ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'not a pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->actingAs($this->maria)->postJson("/api/v1/deals/{$deal->uuid}/files", ['file' => disclosurePdf()])->assertNotFound();

        connectOnWeb($this, $this->james, tool: 'docusign');
        $this->actingAs($this->james)->postJson("/api/v1/deals/{$deal->uuid}/signatures", ['subject' => 'Amendment', 'files' => ['0b8e6d1c-4f2a-4e5b-9c3d-7a1f2e3d4c5b'], 'signers' => [['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'routing_order' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('files');
    });

    it('virus-scans every PDF: an infected one is removed, never opens and can\'t be sent', function () {
        $deal = signingReady($this);
        $this->app->bind(FileScanner::class, fn () => new class implements FileScanner
        {
            public function scan($stream): ScanResult
            {
                return ScanResult::Infected;
            }
        });

        $added = $this->actingAs($this->james)->postJson("/api/v1/deals/{$deal->uuid}/files", ['file' => disclosurePdf()])->assertCreated()
            ->assertJsonPath('data.scan', 'infected')->assertJsonPath('data.url', null);
        $file = DealFile::query()->sole();
        expect(Storage::disk(config()->string('reos.content.disk'))->exists($file->path))->toBeFalse();

        $this->postJson("/api/v1/deals/{$deal->uuid}/signatures", ['subject' => 'Amendment', 'files' => [$added->json('data.uuid')], 'signers' => [['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'routing_order' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->get(URL::temporarySignedRoute('deals.files.show', now()->addMinutes(5), ['file' => $file->uuid]))->assertNotFound();
    });

    it('syncs at once when DocuSign Connect reports an event, for the connection named in the address only', function () {
        signingReady($this);
        $docusign = Connection::query()->where('integration_id', 'docusign')->sole();
        Queue::fake();
        $body = (string) json_encode(['event' => 'envelope-completed', 'generatedDateTime' => '2026-11-23T15:00:00Z', 'data' => ['envelopeId' => 'env-1']]);
        $send = fn (string $c) => $this->call('POST', '/webhooks/docusign?c='.$c, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $send($docusign->id.'.forged000000000000000000000000000')->assertStatus(401);
        Queue::assertNothingPushed();
        $send(WebhookTarget::seal($docusign->id, DocuSignDriver::PURPOSE))->assertSuccessful();
        Queue::assertPushed(SyncConnection::class);
    });
});

describe('the real DocuSign driver', function () {
    it('makes a draft envelope with the documents, signers and Connect address, then the sender view, then reads signers and the signed copy', function () {
        $connection = Connection::query()->forceCreate(['integration_id' => 'docusign', 'owner_type' => Connection::OWNER_USER, 'owner_id' => $this->james->id, 'connected_by' => $this->james->id, 'mode' => IntegrationMode::Sandbox, 'external_account_id' => 'sub-1', 'account_label' => 'james@example.com', 'scopes' => ['signature'], 'status' => 'active', 'settings' => [], 'cursors' => [], 'meta' => []]);
        Http::fake([
            'account-d.docusign.com/oauth/userinfo' => Http::response(['sub' => 'sub-1', 'email' => 'james@example.com', 'accounts' => [['account_id' => 'acc-9', 'is_default' => true, 'base_uri' => 'https://demo.docusign.net']]]),
            'demo.docusign.net/restapi/v2.1/accounts/acc-9/envelopes' => Http::response(['envelopeId' => 'env-77', 'status' => 'created'], 201),
            'demo.docusign.net/restapi/v2.1/accounts/acc-9/envelopes/env-77/views/sender' => Http::response(['url' => 'https://appdemo.docusign.com/send/abc']),
            'demo.docusign.net/restapi/v2.1/accounts/acc-9/envelopes/env-77' => Http::response(['envelopeId' => 'env-77', 'status' => 'completed']),
            'demo.docusign.net/restapi/v2.1/accounts/acc-9/envelopes/env-77/recipients' => Http::response(['signers' => [['email' => 'priya@example.com', 'name' => 'Priya Shah', 'routingOrder' => '1', 'status' => 'completed', 'signedDateTime' => '2026-11-23T15:00:00Z']]]),
            'demo.docusign.net/restapi/v2.1/accounts/acc-9/envelopes/env-77/documents/*' => Http::response('%PDF-1.4 signed', 200, ['Content-Type' => 'application/pdf']),
        ]);
        $driver = new DocuSignDriver('ik', 'secret', DocuSignDriver::DEMO_AUTH, 'hmac-key');

        $id = $driver->draft($connection, 'tok', 'Amendment', [['name' => 'Amendment.pdf', 'contents' => '%PDF-1.4 x']], [['name' => 'Priya Shah', 'email' => 'priya@example.com', 'routing_order' => 1]], 'https://reos.com/webhooks/docusign');
        expect($id)->toBe('env-77')
            ->and($driver->senderView($connection, 'tok', 'env-77', 'https://reos.com/deals/signatures/x/returned'))->toBe('https://appdemo.docusign.com/send/abc')
            ->and($driver->envelope($connection, 'tok', 'env-77'))->toBe(['status' => 'completed', 'signers' => [['email' => 'priya@example.com', 'name' => 'Priya Shah', 'routing_order' => 1, 'status' => 'signed', 'at' => '2026-11-23T15:00:00Z']]])
            ->and($driver->signedCopy($connection, 'tok', 'env-77'))->toBe(['signed' => '%PDF-1.4 signed', 'certificate' => '%PDF-1.4 signed'])
            ->and($connection->refresh()->meta['docusign'])->toBe(['account_id' => 'acc-9', 'base_uri' => 'https://demo.docusign.net']);

        Http::assertSent(fn ($r) => $r->url() === 'https://demo.docusign.net/restapi/v2.1/accounts/acc-9/envelopes' && $r['status'] === 'created'
            && $r['documents'][0]['documentBase64'] === base64_encode('%PDF-1.4 x') && $r['recipients']['signers'][0]['routingOrder'] === '1'
            && str_starts_with($r['eventNotification']['url'], 'https://reos.com/webhooks/docusign?c='.$connection->id.'.') && $r['eventNotification']['includeHMAC'] === 'true');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/views/sender') && $r['returnUrl'] === 'https://reos.com/deals/signatures/x/returned');
        // userinfo once: the account is kept on the connection.
        Http::assertSentCount(7);
    });

    it('signs in with PKCE at the demo or live account server, and checks Connect\'s HMAC when a key is set', function () {
        $demo = new DocuSignDriver('ik', 'secret');
        $live = new DocuSignDriver('ik', 'secret', DocuSignDriver::LIVE_AUTH, 'hmac-key');
        expect($demo->authorizationUrl('st', 'ch', 'https://reos.com/cb'))->toStartWith('https://account-d.docusign.com/oauth/auth?')->toContain('code_challenge=ch')->toContain('scope=signature')
            ->and($live->authorizationUrl('st', 'ch', 'https://reos.com/cb'))->toStartWith('https://account.docusign.com/oauth/auth?');

        $body = (string) json_encode(['event' => 'recipient-completed', 'generatedDateTime' => '2026-11-23T15:00:00Z', 'data' => ['envelopeId' => 'env-77']]);
        $request = fn (?string $signature) => Request::create('/webhooks/docusign?c='.WebhookTarget::seal(42, DocuSignDriver::PURPOSE), 'POST', server: array_filter(['HTTP_X_DOCUSIGN_SIGNATURE_1' => $signature]), content: $body);

        expect($live->verifyWebhook($request(base64_encode(hash_hmac('sha256', $body, 'hmac-key', true))))?->connectionIds)->toBe([42])
            ->and($live->verifyWebhook($request('wrong')))->toBeNull()
            ->and($live->verifyWebhook($request(null)))->toBeNull()
            ->and($demo->verifyWebhook($request(null))?->deliveryKey)->toBe('docusign:env-77:recipient-completed:2026-11-23T15:00:00Z');
    });
});
