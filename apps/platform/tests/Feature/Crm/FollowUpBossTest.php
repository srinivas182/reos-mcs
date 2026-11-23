<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\FollowUpBoss\FollowUpBossDriver;
use App\Connections\Drivers\FollowUpBoss\FollowUpBossSimulator;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\IntegrationSetting;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
});

function fubOn(IntegrationMode $mode, array $keys = []): void
{
    if ($keys !== []) {
        app(CredentialVault::class)->put('follow-up-boss', $mode === IntegrationMode::Live ? CredentialEnvironment::Live : CredentialEnvironment::Sandbox, $keys, null);
    }

    IntegrationSetting::query()->create(['integration_id' => 'follow-up-boss', 'mode' => $mode]);
}

function fubConnect(object $test, string $key = 'demo-key-123'): Connection
{
    $id = $test->actingAs($test->james)->postJson('/api/v1/connections', ['integration' => 'follow-up-boss', 'api_key' => $key])
        ->assertOk()->assertJsonPath('authorization_url', null)->json('connection_id');

    return Connection::query()->findOrFail($id);
}

describe('Follow Up Boss on demo data (the B7.2 done-when)', function () {
    it('connects with an API key and syncs people, notes and tasks into REOS', function () {
        fubOn(IntegrationMode::Simulator);
        $connection = fubConnect($this);

        $contacts = Contact::query()->where('owner_id', $this->james->id)->orderBy('id')->get();
        expect($connection->account_label)->toBe('Follow Up Boss (demo)')
            ->and($contacts->map->name()->all())->toBe(['Maria Lopez', 'David Chen', 'Priya Shah', 'Mike Johnson'])
            ->and(ContactNote::query()->where('source', 'follow-up-boss')->sole()->body)->toContain('Katy ISD')
            ->and(Todo::query()->where('user_id', $this->james->id)->sole())->title->toBe('Send David the listing agreement')
            ->and(Todo::query()->sole()->related_label)->toBe('David Chen');

        $this->getJson('/api/v1/catalogue')->assertOk();
    });

    it('sends a note written in REOS to Follow Up Boss, and brings one written there into REOS', function () {
        fubOn(IntegrationMode::Simulator);
        $connection = fubConnect($this);
        $maria = Contact::query()->where('first_name', 'Maria')->sole();

        $this->postJson("/api/v1/contacts/{$maria->uuid}/notes", ['body' => 'Toured 2218 Elm St; loved the yard.'])->assertCreated()
            ->assertJsonPath('data.sent_to', ['Follow Up Boss']);

        $inTool = collect(FollowUpBossSimulator::records($connection, 'notes'))->firstWhere('body', 'Toured 2218 Elm St; loved the yard.');
        expect($inTool)->not->toBeNull()->and($inTool['personId'])->toBe('1001');

        // Written in Follow Up Boss; arrives on the next sync (a webhook in real life).
        FollowUpBossSimulator::changeInTool($connection, 'notes', null, ['personId' => '1001', 'subject' => 'Text', 'body' => 'Pre-approval letter received.']);
        $this->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();

        $notes = $this->getJson("/api/v1/contacts/{$maria->uuid}")->assertOk()->json('notes');
        expect(collect($notes)->pluck('body')->all())->toContain("Text\nPre-approval letter received.")
            // REOS's own note isn't read back as a second copy.
            ->and(ContactNote::query()->where('body', 'Toured 2218 Elm St; loved the yard.')->count())->toBe(1)
            ->and(ContactNote::query()->where('contact_id', $maria->id)->count())->toBe(3);
    });

    it('marks a task done in both directions, and sends a new stage', function () {
        fubOn(IntegrationMode::Simulator);
        $connection = fubConnect($this);
        $todo = Todo::query()->sole();

        $this->postJson("/api/v1/todos/{$todo->id}/done")->assertOk();
        expect(collect(FollowUpBossSimulator::records($connection, 'tasks'))->sole()['isCompleted'])->toBeTrue();

        FollowUpBossSimulator::changeInTool($connection, 'tasks', '7001', ['isCompleted' => false]);
        $this->postJson("/api/v1/connections/{$connection->id}/sync");
        expect($todo->refresh()->done_at)->toBeNull();

        $david = Contact::query()->where('first_name', 'David')->sole();
        $this->patchJson("/api/v1/contacts/{$david->uuid}", ['stage' => 'Under contract'])->assertOk()->assertJsonPath('data.stage', 'Under contract');
        expect(collect(FollowUpBossSimulator::records($connection, 'people'))->firstWhere('id', '1002')['stage'])->toBe('Under contract');

        // The new stage coming back from Follow Up Boss is no change.
        $this->postJson("/api/v1/connections/{$connection->id}/sync");
        expect($david->refresh()->stage)->toBe('Under contract');
    });

    it('keeps a person deleted in Follow Up Boss, and says so', function () {
        fubOn(IntegrationMode::Simulator);
        $connection = fubConnect($this);

        FollowUpBossSimulator::changeInTool($connection, 'people', '1003', [], deleted: true);
        $this->postJson("/api/v1/connections/{$connection->id}/sync");

        $priya = Contact::query()->where('first_name', 'Priya')->sole();
        expect($priya->links()->count())->toBe(0);
        expect(collect($this->getJson('/api/v1/contacts/activity')->json('data'))->pluck('text'))->toContain('Priya Shah was removed in Follow Up Boss. The contact stays in REOS.');
    });

    it('refuses a short key, and says the catalogue connects it with a key', function () {
        fubOn(IntegrationMode::Simulator);

        $this->actingAs($this->james)->postJson('/api/v1/connections', ['integration' => 'follow-up-boss'])->assertUnprocessable()->assertJsonValidationErrors('api_key');
        $this->postJson('/api/v1/connections', ['integration' => 'follow-up-boss', 'api_key' => 'abc'])->assertUnprocessable();
        expect(Connection::query()->count())->toBe(0);
        expect(collect($this->getJson('/api/v1/catalogue')->json('data'))->firstWhere('id', 'follow-up-boss')['connects_with'] ?? null)->toBe('api_key');
    });
});

describe('the real driver (contract, recorded formats)', function () {
    it('checks the key, sends the system headers, pages people and writes notes', function () {
        fubOn(IntegrationMode::Sandbox, ['system_name' => 'REOS', 'system_key' => 'sys-key-1']);
        Queue::fake();
        Http::fake([
            'api.followupboss.com/v1/me' => Http::response(['id' => 7, 'name' => 'James Miller', 'email' => 'james@lonestar.example', 'account' => ['id' => 99]]),
            'api.followupboss.com/v1/people*' => Http::sequence()
                ->push(['_metadata' => ['next' => 'abc'], 'people' => [['id' => 1001, 'firstName' => 'Maria', 'lastName' => 'Lopez', 'emails' => [['value' => 'maria@example.com']], 'phones' => [['value' => '(832) 555-0123']], 'stage' => 'Lead', 'tags' => ['Buyer'], 'updated' => '2026-11-20T10:00:00Z']]])
                ->push(['_metadata' => ['next' => null], 'people' => [['id' => 1002, 'firstName' => 'David', 'lastName' => 'Chen', 'emails' => [], 'phones' => [], 'updated' => '2026-11-21T10:00:00Z']]]),
            'api.followupboss.com/v1/notes' => Http::response(['id' => 5012]),
        ]);

        $connection = fubConnect($this, 'fka_real_key');
        expect($connection->external_account_id)->toBe('99:7')->and($connection->account_label)->toBe('james@lonestar.example');
        Queue::assertPushed(SyncConnection::class, 3);

        $driver = new FollowUpBossDriver('REOS', 'sys-key-1');
        $first = $driver->fetch($connection, 'fka_real_key', 'people', null, null);
        $second = $driver->fetch($connection, 'fka_real_key', 'people', $first->cursor, null);

        expect($first->done)->toBeFalse()->and($first->records[0])->toMatchArray(['external_id' => '1001', 'emails' => ['maria@example.com'], 'stage' => 'Lead', 'tags' => ['Buyer']])
            ->and($second->done)->toBeTrue()->and(json_decode((string) $second->cursor, true))->toBe(['since' => '2026-11-21T10:00:00Z']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/people') && str_contains($r->url(), 'next=abc') && $r->header('X-System-Key') === ['sys-key-1']
            && $r->header('Authorization') === ['Basic '.base64_encode('fka_real_key:')]);

        expect($driver->createNote($connection, 'fka_real_key', '1001', 'Hello'))->toBe('5012');
        Http::assertSent(fn ($r) => $r->url() === FollowUpBossDriver::BASE.'/notes' && $r['personId'] === 1001 && $r['body'] === 'Hello');
    });

    it('turns a refused key into "check your key"', function () {
        fubOn(IntegrationMode::Sandbox, ['system_name' => 'REOS', 'system_key' => 'sys-key-1']);
        Http::fake(['api.followupboss.com/v1/me' => Http::response(['errorMessage' => 'Invalid API key'], 401)]);

        $this->actingAs($this->james)->postJson('/api/v1/connections', ['integration' => 'follow-up-boss', 'api_key' => 'wrong-key'])
            ->assertUnprocessable()->assertJsonPath('errors.api_key.0', "The tool didn't accept that API key. Check it and try again.");
    });

    it('accepts only webhooks signed with the system key, for the connection named in the address', function () {
        fubOn(IntegrationMode::Sandbox, ['system_name' => 'REOS', 'system_key' => 'sys-key-1']);
        Http::fake(['api.followupboss.com/v1/me' => Http::response(['id' => 7, 'account' => ['id' => 99]])]);
        Queue::fake();
        $connection = fubConnect($this, 'fka_real_key');
        Queue::fake();
        $body = (string) json_encode(['eventId' => 'evt-1', 'event' => 'peopleDeleted', 'resourceIds' => [1003], 'uri' => 'https://api.followupboss.com/v1/people?id=1003']);
        $signed = hash_hmac('sha256', base64_encode($body), 'sys-key-1');
        $url = '/webhooks/follow-up-boss?c='.WebhookTarget::seal($connection->id);

        $this->call('POST', $url, [], [], [], ['HTTP_FUB-Signature' => 'bad', 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);
        $this->call('POST', '/webhooks/follow-up-boss?c='.$connection->id.'.forged000000000000000000000000000', [], [], [], ['HTTP_FUB-Signature' => $signed, 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);
        Queue::assertNothingPushed();

        $this->call('POST', $url, [], [], [], ['HTTP_FUB-Signature' => $signed, 'CONTENT_TYPE' => 'application/json'], $body)->assertSuccessful();
        Queue::assertPushed(SyncConnection::class, 3);
        expect($connection->refresh()->meta['deleted_people'])->toBe(['1003']);

        // The same delivery again changes nothing.
        $this->call('POST', $url, [], [], [], ['HTTP_FUB-Signature' => $signed, 'CONTENT_TYPE' => 'application/json'], $body)->assertSuccessful();
        Queue::assertPushed(SyncConnection::class, 3);
    });
});
