<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\FollowUpBoss\FollowUpBossDriver;
use App\Connections\Drivers\Lofty\LoftyDriver;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactLink;
use App\Models\IntegrationSetting;
use App\Models\SimulatorRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    foreach (['follow-up-boss', 'boldtrail', 'lofty'] as $tool) {
        IntegrationSetting::query()->create(['integration_id' => $tool, 'mode' => IntegrationMode::Simulator]);
    }
});

function crmConnect(object $test, string $tool, string $key): Connection
{
    $id = $test->actingAs($test->james)->postJson('/api/v1/connections', ['integration' => $tool, 'api_key' => $key])->assertOk()->json('connection_id');

    return Connection::query()->findOrFail($id);
}

function csvFile(string $body, string $name = 'open-house.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $body);
}

describe('where new contacts go (screen 24)', function () {
    it('uses the only CRM that takes new people, and lets the agent pick when there are two', function () {
        $fub = crmConnect($this, 'follow-up-boss', 'fub-demo-key');
        crmConnect($this, 'boldtrail', 'kv-demo-token');

        $crms = $this->actingAs($this->james)->getJson('/api/v1/contacts/crms')->assertOk()->json('data');
        expect(collect($crms)->firstWhere('integration', 'follow-up-boss'))->toMatchArray(['takes_new_people' => true, 'is_default' => true])
            ->and(collect($crms)->firstWhere('integration', 'boldtrail'))->toMatchArray(['takes_new_people' => false, 'is_default' => false]);

        $lofty = crmConnect($this, 'lofty', 'lofty-demo-key');
        expect(collect($this->getJson('/api/v1/contacts/crms')->json('data'))->where('is_default', true))->toBeEmpty();

        $this->putJson('/api/v1/contacts/crms/default', ['connection' => $lofty->id])->assertOk();
        expect(collect($this->getJson('/api/v1/contacts/crms')->json('data'))->firstWhere('is_default', true)['connection'])->toBe($lofty->id);

        // BoldTrail can't take new people; someone else's connection isn't yours to pick.
        $kv = Connection::query()->where('integration_id', 'boldtrail')->sole();
        $this->putJson('/api/v1/contacts/crms/default', ['connection' => $kv->id])->assertStatus(422);
        $other = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($other)->putJson('/api/v1/contacts/crms/default', ['connection' => $fub->id])->assertStatus(422);
    });

    it('adds a contact in REOS and in the default CRM, and reads it back as the same person', function () {
        $lofty = crmConnect($this, 'lofty', 'lofty-demo-key');

        $response = $this->actingAs($this->james)->postJson('/api/v1/contacts', ['first_name' => 'Nora', 'last_name' => 'Patel', 'email' => 'nora.patel@example.com', 'phone' => '(713) 555-0166'])
            ->assertCreated()->assertJsonPath('sent_to', 'Lofty');
        $nora = Contact::query()->where('uuid', $response->json('data.id'))->sole();
        $link = ContactLink::query()->where('contact_id', $nora->id)->where('connection_id', $lofty->id)->sole();

        expect(SimulatorRecord::query()->where('stream', 'people')->where('external_id', $link->external_id)->sole()->data['firstName'])->toBe('Nora');

        $this->postJson("/api/v1/connections/{$lofty->id}/sync")->assertAccepted();
        expect(Contact::query()->where('first_name', 'Nora')->count())->toBe(1)
            ->and($nora->refresh()->links()->pluck('source')->sort()->values()->all())->toBe(['lofty', 'reos']);
    });

    it('joins a person already known by email instead of adding them twice', function () {
        crmConnect($this, 'follow-up-boss', 'fub-demo-key');
        $before = Contact::query()->count();

        $this->actingAs($this->james)->postJson('/api/v1/contacts', ['first_name' => 'Maria', 'email' => 'MARIA.LOPEZ@example.com'])->assertCreated()->assertJsonPath('sent_to', 'Follow Up Boss');
        expect(Contact::query()->count())->toBe($before)
            ->and(SimulatorRecord::query()->where('stream', 'people')->get()->filter(fn ($r) => ($r->data['firstName'] ?? null) === 'Maria'))->toHaveCount(1);
    });

    it('keeps a contact in REOS when no CRM takes new people', function () {
        $this->actingAs($this->james)->postJson('/api/v1/contacts', ['first_name' => 'Aunt Rosa', 'phone' => '713-555-0111'])->assertCreated()->assertJsonPath('sent_to', null);
        $this->postJson('/api/v1/contacts', [])->assertUnprocessable()->assertJsonValidationErrors('first_name');
        $this->postJson('/api/v1/contacts', ['phone' => 'call me'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    });

    it('sends a new task to the default CRM when it knows the person', function () {
        crmConnect($this, 'follow-up-boss', 'fub-demo-key');
        $lofty = crmConnect($this, 'lofty', 'lofty-demo-key');
        $this->actingAs($this->james)->putJson('/api/v1/contacts/crms/default', ['connection' => Connection::query()->where('integration_id', 'follow-up-boss')->sole()->id])->assertOk();
        // Lofty synced most recently, but the default wins.
        $lofty->forceFill(['last_synced_at' => now()->addMinute()])->save();
        ContactLink::query()->where('connection_id', $lofty->id)->update(['synced_at' => now()->addMinute()]);
        $maria = Contact::query()->where('first_name', 'Maria')->sole();

        $this->postJson("/api/v1/contacts/{$maria->uuid}/tasks", ['title' => 'Send Maria the HOA rules'])->assertCreated()->assertJsonPath('sent_to', 'Follow Up Boss');
    });
});

describe('the real drivers create people (documented formats)', function () {
    it('posts to Follow Up Boss with deduplicate, and to Lofty with its limits', function () {
        Http::fake([
            'api.followupboss.com/v1/people*' => Http::response(['id' => 501]),
            'api.lofty.com/v1.0/leads' => Http::response(['leadId' => 9001]),
        ]);
        $connection = new Connection(['integration_id' => 'x']);
        $person = ['first_name' => 'Nora', 'last_name' => str_repeat('P', 40), 'emails' => ['nora@example.com'], 'phones' => ['+17135550166'], 'stage' => 'Open house sign-in'];

        expect((new FollowUpBossDriver('REOS', 'sys-key-1'))->createPerson($connection, 'key', $person))->toBe('501')
            ->and((new LoftyDriver)->createPerson($connection, 'key', $person))->toBe('9001');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'people?deduplicate=true') && $r['emails'] === [['value' => 'nora@example.com', 'type' => 'home']] && $r['stage'] === 'Open house sign-in');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1.0/leads') && mb_strlen($r['lastName']) === 30 && $r['stage'] === 'Open house sign-in' && $r['phones'] === ['+17135550166']);
    });
});

describe('CSV import, the last resort', function () {
    beforeEach(function () {
        Storage::fake('local');
        crmConnect($this, 'follow-up-boss', 'fub-demo-key');
    });

    it('maps, previews, imports, names the source and undoes', function () {
        $csv = "\u{FEFF}Name,E-mail,Mobile,Status,Notes\n"
            ."\"Lopez, Maria\",maria.lopez@example.com,,Past client,already known\n"
            ."Nora Patel,nora.patel@example.com,713-555-0166,Lead,\n"
            ."Nora Patel,NORA.PATEL@example.com,,,second row same person\n"
            .",,,,blank row\n";

        $this->actingAs($this->james)->post('/contacts/import', ['file' => csvFile($csv)])->assertRedirect();
        $import = ContactImport::query()->sole();
        expect($import->headers)->toBe(['Name', 'E-mail', 'Mobile', 'Status', 'Notes'])
            ->and($import->rows)->toBe(4)
            ->and($import->mapping)->toBe(['Name' => 'full_name', 'E-mail' => 'email', 'Mobile' => 'phone', 'Status' => 'stage']);

        $this->get('/contacts/import?import='.$import->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('contacts/import')->where('current.rows', 4)->where('current.mapping.Name', 'full_name'));

        $this->put("/contacts/import/{$import->uuid}", ['mapping' => $import->mapping + ['Notes' => 'ignore']])->assertRedirect()
            ->assertSessionHas('import_preview', function (array $p) {
                return $p['new'] === 1 && $p['merged'] === 2 && $p['skipped'] === 1 && $p['samples'][0]['joins'] === 'Maria Lopez' && $p['samples'][0]['outcome'] === 'merged';
            });

        $this->post("/contacts/import/{$import->uuid}")->assertRedirect()->assertSessionHas('success', __('crm.import.started'));
        $import->refresh();
        $nora = Contact::query()->where('first_name', 'Nora')->sole();
        expect($import->status)->toBe('imported')
            ->and($import->counts)->toBe(['new' => 1, 'merged' => 2, 'skipped' => 1])
            ->and($import->path)->toBeNull()
            ->and($nora->stage)->toBe('Lead')
            ->and(collect($this->getJson("/api/v1/contacts/{$nora->uuid}")->json('data.sources'))->pluck('name')->all())->toBe([__('crm.sources.csv', ['date' => $import->created_at?->isoFormat('ll')])]);

        $this->delete("/contacts/import/{$import->uuid}")->assertRedirect();
        expect(Contact::query()->where('first_name', 'Nora')->exists())->toBeFalse()
            ->and(Contact::query()->where('first_name', 'Maria')->sole()->links()->pluck('source')->all())->toBe(['follow-up-boss'])
            ->and($import->refresh()->status)->toBe('undone');
    });

    it('refuses files it can\'t use, and mappings with nothing to match on', function () {
        $this->actingAs($this->james)->post('/contacts/import', ['file' => csvFile("Name,Email\n")])->assertSessionHasErrors(['file' => __('crm.import.errors.empty')]);
        $this->post('/contacts/import', ['file' => UploadedFile::fake()->create('photo.png', 10, 'image/png')])->assertSessionHasErrors('file');

        $this->post('/contacts/import', ['file' => csvFile("First;Last;Phone\nAna;Ruiz;713-555-0100\n")])->assertRedirect();
        $import = ContactImport::query()->sole();
        expect($import->mapping)->toBe(['First' => 'first_name', 'Last' => 'last_name', 'Phone' => 'phone']);
        $this->put("/contacts/import/{$import->uuid}", ['mapping' => ['First' => 'stage']])->assertSessionHasErrors(['mapping' => __('crm.import.errors.noName')]);
        $this->put("/contacts/import/{$import->uuid}", ['mapping' => ['First' => 'password']])->assertSessionHasErrors('mapping.First');
    });

    it('is the agent\'s own', function () {
        $this->actingAs($this->james)->post('/contacts/import', ['file' => csvFile("Name,Email\nNora Patel,nora@example.com\n")]);
        $import = ContactImport::query()->sole();
        $other = User::factory()->withRole(Role::Agent)->create();

        $this->actingAs($other)->put("/contacts/import/{$import->uuid}", ['mapping' => ['Name' => 'full_name']])->assertNotFound();
        $this->actingAs($other)->post("/contacts/import/{$import->uuid}")->assertNotFound();
        $this->actingAs($other)->get('/contacts/import?import='.$import->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->where('current', null));
    });
});

describe('screen 13 on the web (B8.3b)', function () {
    it('adds a contact and picks the default CRM from the hub', function () {
        $fub = crmConnect($this, 'follow-up-boss', 'fub-demo-key');
        $lofty = crmConnect($this, 'lofty', 'lofty-demo-key');

        $this->actingAs($this->james)->get('/contacts')->assertInertia(fn (Assert $page) => $page->has('crms', 2)->where('crms.0.is_default', false));
        $this->put('/contacts/default-crm', ['connection' => $fub->id])->assertSessionHas('success', __('crm.add.defaultSaved'));
        $this->post('/contacts', ['first_name' => 'Nora', 'email' => 'nora@example.com'])->assertRedirect()->assertSessionHas('success', __('crm.add.sent', ['tool' => 'Follow Up Boss']));
        expect(Contact::query()->where('first_name', 'Nora')->sole()->links()->where('connection_id', $fub->id)->exists())->toBeTrue()
            ->and(Contact::query()->where('first_name', 'Nora')->sole()->links()->where('connection_id', $lofty->id)->exists())->toBeFalse();
    });
});
