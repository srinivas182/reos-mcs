<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Connections\Drivers\Lofty\LoftyDriver;
use App\Connections\Drivers\Lofty\LoftySimulator;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\IntegrationSetting;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
});

function loftyOn(IntegrationMode $mode): void
{
    IntegrationSetting::query()->create(['integration_id' => 'lofty', 'mode' => $mode]);
}

function loftyConnect(object $test, string $key = 'lofty-demo-key'): Connection
{
    $id = $test->actingAs($test->james)->postJson('/api/v1/connections', ['integration' => 'lofty', 'api_key' => $key])->assertOk()->json('connection_id');

    return Connection::query()->findOrFail($id);
}

describe('Lofty on demo data (the B8.1 done-when)', function () {
    it('connects with an API key and brings in leads, notes and tasks', function () {
        loftyOn(IntegrationMode::Simulator);
        $connection = loftyConnect($this);

        $priya = Contact::query()->where('first_name', 'Priya')->sole();
        expect($connection->account_label)->toBe('Lofty (demo)')
            ->and(Contact::query()->where('first_name', 'Maria')->sole()->stage)->toBe('Nurture')
            ->and(array_column($priya->emails, 'value'))->toContain('priya.shah@example.com')
            ->and(ContactNote::query()->where('source', 'lofty')->sole()->body)->toContain('Sugar Land schools')
            ->and(Todo::query()->sole()->title)->toBe('Send Sugar Land listings to Priya');
    });

    it('sends a note written in REOS to Lofty, and brings one written in Lofty into REOS', function () {
        loftyOn(IntegrationMode::Simulator);
        $connection = loftyConnect($this);
        $priya = Contact::query()->where('first_name', 'Priya')->sole();

        $this->postJson("/api/v1/contacts/{$priya->uuid}/notes", ['body' => 'Booked a Saturday tour.'])->assertCreated()->assertJsonPath('data.sent_to', ['Lofty']);
        expect(collect(LoftySimulator::records($connection, 'notes'))->pluck('content'))->toContain('Booked a Saturday tour.');

        LoftySimulator::changeInTool($connection, 'notes', '7002', ['leadId' => '3002', 'content' => 'Lender called: rate locked.']);
        $this->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();
        expect(ContactNote::query()->where('contact_id', $priya->id)->pluck('body')->all())->toContain('Lender called: rate locked.')
            ->and(ContactNote::query()->where('body', 'Booked a Saturday tour.')->count())->toBe(1);
    });

    it('writes task completion, stage and tags back', function () {
        loftyOn(IntegrationMode::Simulator);
        $connection = loftyConnect($this);
        $priya = Contact::query()->where('first_name', 'Priya')->sole();

        $this->postJson('/api/v1/todos/'.Todo::query()->sole()->id.'/done')->assertOk();
        expect(collect(LoftySimulator::records($connection, 'tasks'))->sole()['finishFlag'])->toBeTrue();

        $this->patchJson("/api/v1/contacts/{$priya->uuid}", ['stage' => 'Active'])->assertOk();
        expect(collect(LoftySimulator::records($connection, 'people'))->firstWhere('id', '3002')['stage'])->toBe('Active');

        LoftySimulator::changeInTool($connection, 'people', '3002', ['stage' => 'Under Contract']);
        $this->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();
        expect($priya->refresh()->stage)->toBe('Under Contract');
    });
});

describe('the real driver (contract, documented Open API formats)', function () {
    it('pages leads, keeps those changed since the mark, and reads their notes and tasks', function () {
        loftyOn(IntegrationMode::Sandbox);
        Queue::fake();
        Http::fake([
            'api.lofty.com/v1.0/leads?limit=1' => Http::response(['get_metadata' => ['total' => 0], 'leads' => []]),
            'api.lofty.com/v1.0/leads?limit=100*' => Http::response(['get_metadata' => ['limit' => 100, 'offset' => 0, 'total' => 2], 'leads' => [
                ['leadId' => 3001, 'firstName' => 'Maria', 'lastName' => 'Lopez', 'emails' => ['maria@example.com'], 'phones' => ['832-555-0123'], 'stage' => 'Nurture',
                    'tags' => [['tagId' => 1, 'tagName' => 'katy-buyer']], 'assignedUser' => 'james', 'lastUpdateTime' => '2026-11-22 10:00:00'],
                ['leadId' => 3003, 'firstName' => 'Old', 'lastName' => 'Lead', 'lastUpdateTime' => '2026-10-01 10:00:00'],
            ]]),
            'api.lofty.com/v1.0/leads?limit=20*' => Http::response(['get_metadata' => ['total' => 1], 'leads' => [['leadId' => 3001, 'lastUpdateTime' => '2026-11-22 10:00:00']]]),
            'api.lofty.com/v1.0/notes?leadId=3001' => Http::response(['notes' => [['noteId' => 55, 'leadId' => 3001, 'content' => 'Called back.', 'lastUpdate' => '2026-11-22 10:00:00']]]),
            'api.lofty.com/v1.0/tasks?leadId=3001' => Http::response(['taskList' => [['id' => 77, 'leadId' => 3001, 'content' => 'Send comps', 'deadline' => '2026-11-25 15:00:00', 'finishFlag' => false, 'lastUpdate' => '2026-11-22 10:00:00']]]),
            'api.lofty.com/v1.0/notes' => Http::response(['noteId' => 56]),
            'api.lofty.com/v2.0/tasks/77/finish' => Http::response(['message' => 'Operation successful']),
            'api.lofty.com/v1.0/leads/3001' => Http::response(['leadId' => 3001]),
        ]);
        $connection = loftyConnect($this, 'lofty-real-key');
        $driver = new LoftyDriver;
        $mark = (string) json_encode(['mark' => '2026-11-01T00:00:00Z']);

        $people = $driver->fetch($connection, 'lofty-real-key', 'people', $mark, null);
        expect($people->done)->toBeTrue()
            ->and($people->records)->toHaveCount(1)
            ->and($people->records[0])->toMatchArray(['external_id' => '3001', 'stage' => 'Nurture', 'tags' => ['katy-buyer'], 'emails' => ['maria@example.com'], 'updated_at' => '2026-11-22T10:00:00Z'])
            ->and(json_decode((string) $people->cursor, true))->toBe(['mark' => '2026-11-22T10:00:00Z']);

        expect($driver->fetch($connection, 'lofty-real-key', 'notes', $mark, null)->records)->toBe([['external_id' => '55', 'person_id' => '3001', 'body' => 'Called back.', 'updated_at' => '2026-11-22T10:00:00Z']])
            ->and($driver->fetch($connection, 'lofty-real-key', 'tasks', $mark, null)->records[0])->toMatchArray(['external_id' => '77', 'title' => 'Send comps', 'done' => false, 'due_at' => '2026-11-25T15:00:00Z']);

        expect($driver->createNote($connection, 'lofty-real-key', '3001', 'Booked a tour.'))->toBe('56');
        $driver->setTaskDone($connection, 'lofty-real-key', '77', true);
        $driver->updatePerson($connection, 'lofty-real-key', '3001', ['stage' => 'Active', 'tags' => ['katy-buyer', 'hot']]);

        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'https://api.lofty.com/v1.0/notes' && $r['leadId'] === 3001 && $r['isPin'] === false
            && $r->header('Authorization') === ['token lofty-real-key'] && $r->header('X-Error-Status') === ['true']);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'https://api.lofty.com/v2.0/tasks/77/finish');
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->url() === 'https://api.lofty.com/v1.0/leads/3001' && $r['stage'] === 'Active' && $r['tags'] === ['katy-buyer', 'hot']);
    });

    it('takes only webhooks to its own signed address, and remembers deleted leads', function () {
        loftyOn(IntegrationMode::Simulator);
        $connection = loftyConnect($this);
        $driver = new LoftyDriver;
        $request = fn (string $c, array $body, ?string $id = 'evt-1') => Request::create('/webhooks/lofty?c='.$c, 'POST', server: array_filter(['HTTP_LOFTY_WEBHOOK_ID' => $id]), content: (string) json_encode($body));

        $delivery = $driver->verifyWebhook($request(WebhookTarget::seal($connection->id, LoftyDriver::PURPOSE), ['teamId' => 1, 'listId' => 2, 'deletedLead' => [['leadId' => 3001, 'updateTime' => 1764000000000]]]));
        expect($delivery?->connectionIds)->toBe([$connection->id])
            ->and($delivery?->deliveryKey)->toBe('lofty:evt-1')
            ->and($connection->refresh()->meta['deleted_people'])->toBe(['3001']);

        // Follow Up Boss's seal, a changed id, no delivery id or another list: refused.
        expect($driver->verifyWebhook($request(WebhookTarget::seal($connection->id), ['listId' => 2])))->toBeNull()
            ->and($driver->verifyWebhook($request(($connection->id + 1).substr(WebhookTarget::seal($connection->id, LoftyDriver::PURPOSE), strlen((string) $connection->id)), ['listId' => 2])))->toBeNull()
            ->and($driver->verifyWebhook($request(WebhookTarget::seal($connection->id, LoftyDriver::PURPOSE), ['listId' => 2], null)))->toBeNull()
            ->and($driver->verifyWebhook($request(WebhookTarget::seal($connection->id, LoftyDriver::PURPOSE), ['listId' => 5])))->toBeNull();
    });

    it('turns a refused key into "check your key"', function () {
        loftyOn(IntegrationMode::Sandbox);
        Http::fake(['api.lofty.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->actingAs($this->james)->postJson('/api/v1/connections', ['integration' => 'lofty', 'api_key' => 'wrong-key'])->assertUnprocessable()->assertJsonValidationErrors('api_key');
    });
});
