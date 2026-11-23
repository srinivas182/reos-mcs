<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\BoldTrail\BoldTrailDriver;
use App\Connections\Drivers\BoldTrail\BoldTrailSimulator;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
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

function kvOn(IntegrationMode $mode): void
{
    if ($mode === IntegrationMode::Sandbox) {
        app(CredentialVault::class)->put('boldtrail', CredentialEnvironment::Sandbox, ['server_url' => 'https://api.kvcore.com/v2/public'], null);
    }

    IntegrationSetting::query()->create(['integration_id' => 'boldtrail', 'mode' => $mode]);
}

function kvConnect(object $test, string $token = 'kv-demo-token'): Connection
{
    $id = $test->actingAs($test->james)->postJson('/api/v1/connections', ['integration' => 'boldtrail', 'api_key' => $token])->assertOk()->json('connection_id');

    return Connection::query()->findOrFail($id);
}

describe('kvCORE / BoldTrail on demo data', function () {
    it('connects with a token and polls contacts, notes, tasks and the smart campaign', function () {
        kvOn(IntegrationMode::Simulator);
        $connection = kvConnect($this);

        $maria = Contact::query()->where('first_name', 'Maria')->sole();
        expect($connection->account_label)->toBe('kvCORE (demo)')
            ->and($maria->stage)->toBe('Active')
            ->and(ContactNote::query()->where('source', 'boldtrail')->sole()->body)->toContain('Relocating from Dallas')
            ->and(Todo::query()->sole()->title)->toBe('Call Tom about Bellaire listings');

        $this->getJson("/api/v1/contacts/{$maria->uuid}")->assertOk()
            ->assertJsonPath('data.sources.0.campaign', 'Buyer nurture: 90 days')
            ->assertJsonPath('data.sources.0.campaign_status', 'Active');
    });

    it('writes notes, task completion and status back; changes there arrive on the next poll', function () {
        kvOn(IntegrationMode::Simulator);
        $connection = kvConnect($this);
        $tom = Contact::query()->where('first_name', 'Tom')->sole();

        $this->postJson("/api/v1/contacts/{$tom->uuid}/notes", ['body' => 'Sent three Bellaire listings.'])->assertCreated()->assertJsonPath('data.sent_to', ['kvCORE / BoldTrail']);
        expect(collect(BoldTrailSimulator::records($connection, 'notes'))->pluck('details'))->toContain('Sent three Bellaire listings.');

        $this->postJson('/api/v1/todos/'.Todo::query()->sole()->id.'/done')->assertOk();
        expect(collect(BoldTrailSimulator::records($connection, 'tasks'))->sole()['completed'])->toBe(1);

        $this->patchJson("/api/v1/contacts/{$tom->uuid}", ['stage' => 'Client'])->assertOk();
        expect(collect(BoldTrailSimulator::records($connection, 'people'))->firstWhere('id', '2002')['status'])->toBe(1);

        BoldTrailSimulator::changeInTool($connection, 'people', '2002', ['status' => 5]);
        $this->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();
        expect($tom->refresh()->stage)->toBe('Contract')
            ->and(ContactNote::query()->where('body', 'Sent three Bellaire listings.')->count())->toBe(1);
    });

    it('matches kvCORE contacts to the same people from Follow Up Boss', function () {
        kvOn(IntegrationMode::Simulator);
        IntegrationSetting::query()->create(['integration_id' => 'follow-up-boss', 'mode' => IntegrationMode::Simulator]);
        $this->actingAs($this->james)->postJson('/api/v1/connections', ['integration' => 'follow-up-boss', 'api_key' => 'fub-demo-key'])->assertOk();
        kvConnect($this);

        $maria = Contact::query()->where('first_name', 'Maria')->sole();
        expect($maria->links()->pluck('source')->sort()->values()->all())->toBe(['boldtrail', 'follow-up-boss']);
    });
});

describe('the real driver (contract, documented V2 formats)', function () {
    it('pages contacts newest first until the last mark, reads changed contacts\' notes, and maps status', function () {
        kvOn(IntegrationMode::Sandbox);
        Queue::fake();
        Http::fake([
            'api.kvcore.com/v2/public/contacts?limit=1' => Http::response(['data' => [], 'current_page' => 1, 'last_page' => 1]),
            'api.kvcore.com/v2/public/contacts?limit=100*' => Http::response(['data' => [
                ['id' => 2001, 'first_name' => 'Maria', 'last_name' => 'Lopez', 'email' => 'maria@example.com', 'cell_phone_1' => '832-555-0123', 'status' => 4, 'date_modified' => '2026-11-22T10:00:00Z', 'smart_campaign' => ['name' => 'Buyer nurture', 'status' => 'Active']],
                ['id' => 2003, 'first_name' => 'Old', 'last_name' => 'Contact', 'date_modified' => '2026-10-01T10:00:00Z'],
            ], 'current_page' => 1, 'last_page' => 5]),
            'api.kvcore.com/v2/public/contacts?limit=20*' => Http::response(['data' => [['id' => 2001, 'date_modified' => '2026-11-22T10:00:00Z']], 'current_page' => 1, 'last_page' => 1]),
            'api.kvcore.com/v2/public/contact/2001/notes' => Http::response(['data' => [['id' => 9, 'details' => 'Called back.', 'updated_at' => '2026-11-22T10:00:00Z']]]),
            'api.kvcore.com/v2/public/contact/2001' => Http::response(['data' => ['id' => 2001]]),
        ]);
        $connection = kvConnect($this, 'kv-real-token');
        $driver = new BoldTrailDriver('https://api.kvcore.com/v2/public');
        $mark = (string) json_encode(['mark' => '2026-11-01T00:00:00Z']);

        $people = $driver->fetch($connection, 'kv-real-token', 'people', $mark, null);
        expect($people->done)->toBeTrue()
            ->and($people->records)->toHaveCount(1)
            ->and($people->records[0])->toMatchArray(['external_id' => '2001', 'stage' => 'Active', 'emails' => ['maria@example.com'], 'details' => ['campaign' => 'Buyer nurture', 'campaign_status' => 'Active']])
            ->and(json_decode((string) $people->cursor, true))->toBe(['mark' => '2026-11-22T10:00:00Z']);

        $notes = $driver->fetch($connection, 'kv-real-token', 'notes', $mark, null);
        expect($notes->records)->toBe([['external_id' => '9', 'person_id' => '2001', 'body' => 'Called back.', 'updated_at' => '2026-11-22T10:00:00Z']]);

        $driver->updatePerson($connection, 'kv-real-token', '2001', ['stage' => 'Contract']);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->url() === 'https://api.kvcore.com/v2/public/contact/2001' && $r['status'] === 5 && $r->header('Authorization') === ['Bearer kv-real-token']);
        expect($driver->verifyWebhook(request()))->toBeNull();
    });

    it('turns a refused token into "check your key"', function () {
        kvOn(IntegrationMode::Sandbox);
        Http::fake(['api.kvcore.com/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->actingAs($this->james)->postJson('/api/v1/connections', ['integration' => 'boldtrail', 'api_key' => 'wrong-token'])->assertUnprocessable()->assertJsonValidationErrors('api_key');
    });
});
