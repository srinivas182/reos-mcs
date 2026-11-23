<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\Salesforce\SalesforceDriver;
use App\Connections\Drivers\Salesforce\SalesforceRecords;
use App\Connections\Drivers\Salesforce\SalesforceSimulator;
use App\Connections\Exceptions\AccessRevoked;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\Todo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->linda = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park', 'email' => 'linda.park@demo.reos.test']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'email' => 'james.miller@demo.reos.test']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Maria Garcia', 'email' => 'Maria.Garcia@demo.reos.test']);
});

/** The broker connects the brokerage's org through the demo sign-in (OAuth with PKCE). */
function salesforceConnected(object $test): Connection
{
    IntegrationSetting::query()->firstOrCreate(['integration_id' => 'salesforce'], ['mode' => IntegrationMode::Simulator]);
    connectOnWeb($test, $test->linda, tool: 'salesforce');

    return Connection::query()->where('integration_id', 'salesforce')->sole();
}

/** @return list<string> */
function namesOf(User $user): array
{
    return Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->orderBy('first_name')->pluck('first_name')->all();
}

describe('Salesforce on demo data (the B8.2 done-when)', function () {
    it('connects the brokerage\'s org and gives each agent only the records they own', function () {
        $connection = salesforceConnected($this);

        expect($connection->owner_type)->toBe(Connection::OWNER_ORGANISATION)
            ->and($connection->owner_id)->toBe($this->lone->id)
            ->and($connection->account_label)->toBe('admin@lonestar-realty.example (demo)')
            ->and(namesOf($this->james))->toBe(['Daniel', 'Leo'])
            ->and(namesOf($this->maria))->toBe(['Grace'])
            // The broker sees none of the agents' contacts; the assistant's walk-in has no agent.
            ->and(Contact::query()->where('owner_type', 'organisation')->count())->toBe(0)
            ->and(namesOf($this->linda))->toBe([])
            ->and(Contact::query()->where('first_name', 'Office')->exists())->toBeFalse()
            ->and($connection->refresh()->meta['unmatched_owners'])->toBe(['assistant@lonestar.example']);

        $daniel = Contact::query()->where('first_name', 'Daniel')->sole();
        expect(ContactNote::query()->where('contact_id', $daniel->id)->sole()->body)->toContain('Heights bungalow')
            ->and(Todo::query()->sole())->user_id->toBe($this->james->id)->title->toBe('Send Daniel the Heights comps');

        $this->actingAs($this->maria)->getJson("/api/v1/contacts/{$daniel->uuid}")->assertNotFound();
    });

    it('sends an agent\'s note to Salesforce, and brings one written there to the agent who owns the person', function () {
        $connection = salesforceConnected($this);
        $daniel = Contact::query()->where('first_name', 'Daniel')->sole();
        $grace = Contact::query()->where('first_name', 'Grace')->sole();

        $this->actingAs($this->james)->postJson("/api/v1/contacts/{$daniel->uuid}/notes", ['body' => 'Offer drafted on the Heights bungalow.'])->assertCreated()->assertJsonPath('data.sent_to', ['Salesforce']);
        expect(collect(SalesforceSimulator::records($connection, 'notes'))->firstWhere('LinkedEntityId', '003sim000000001')['TextPreview'] ?? null)->not->toBeNull()
            ->and(collect(SalesforceSimulator::records($connection, 'notes'))->pluck('TextPreview'))->toContain('Offer drafted on the Heights bungalow.');

        SalesforceSimulator::changeInTool($connection, 'notes', '069sim000000002', ['Title' => 'Annual check-in', 'TextPreview' => 'Thinking about selling in spring.', 'LinkedEntityId' => '003sim000000002']);
        $this->actingAs($this->linda)->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();

        expect(ContactNote::query()->where('contact_id', $grace->id)->pluck('body')->all())->toContain("Annual check-in\nThinking about selling in spring.")
            ->and(ContactNote::query()->where('body', 'Offer drafted on the Heights bungalow.')->count())->toBe(1);
    });

    it('writes task status back, and a stage only to the field the brokerage mapped', function () {
        $connection = salesforceConnected($this);
        $daniel = Contact::query()->where('first_name', 'Daniel')->sole();
        $leo = Contact::query()->where('first_name', 'Leo')->sole();

        $this->actingAs($this->james)->postJson('/api/v1/todos/'.Todo::query()->sole()->id.'/done')->assertOk();
        expect(collect(SalesforceSimulator::records($connection, 'tasks'))->sole())->toMatchArray(['Status' => 'Completed', 'IsClosed' => true]);

        // A Lead's stage is its Status; a Contact has none until the brokerage maps one.
        $this->patchJson("/api/v1/contacts/{$leo->uuid}", ['stage' => 'Working - Contacted'])->assertOk();
        $this->patchJson("/api/v1/contacts/{$daniel->uuid}", ['stage' => 'Under contract'])->assertOk();
        $people = collect(SalesforceSimulator::records($connection, 'people'));
        expect($people->firstWhere('Id', '00Qsim000000001')['Status'])->toBe('Working - Contacted')
            ->and($people->firstWhere('Id', '003sim000000001')['Stage__c'])->toBe('Active buyer');

        $connection->forceFill(['meta' => [...$connection->meta, 'field_map' => ['contact_stage' => 'Stage__c', 'lead_stage' => 'Status', 'leads' => true]]])->save();
        $this->patchJson("/api/v1/contacts/{$daniel->uuid}", ['stage' => 'Closed won'])->assertOk();
        expect(collect(SalesforceSimulator::records($connection, 'people'))->firstWhere('Id', '003sim000000001')['Stage__c'])->toBe('Closed won');
    });

    it('drops people deleted in Salesforce, and gives a new record to the agent who owns it, whatever the email\'s case', function () {
        $connection = salesforceConnected($this);
        SalesforceSimulator::changeInTool($connection, 'people', '00Qsim000000001', [], deleted: true);
        SalesforceSimulator::changeInTool($connection, 'people', '003sim000000004', ['FirstName' => 'Nina', 'LastName' => 'Patel', 'Email' => 'nina@example.com', 'Owner' => ['Email' => 'MARIA.GARCIA@demo.reos.test']]);

        $this->actingAs($this->linda)->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();

        // Leo is no longer in Salesforce: his link goes (REOS keeps the person, as for every CRM).
        expect(Contact::query()->where('first_name', 'Leo')->sole()->links()->where('source', 'salesforce')->exists())->toBeFalse()
            ->and(namesOf($this->maria))->toBe(['Grace', 'Nina']);
    });
});

describe('the real driver (contract, documented REST formats)', function () {
    it('signs in with PKCE, keeps the org\'s address with the session, and reads changes by SystemModstamp', function () {
        Http::fake([
            'login.salesforce.com/services/oauth2/token' => Http::response(['access_token' => '00Dxx!session', 'refresh_token' => 'refresh-1', 'instance_url' => 'https://lonestar.my.salesforce.com', 'scope' => 'api refresh_token']),
            'lonestar.my.salesforce.com/services/oauth2/userinfo' => Http::response(['organization_id' => '00Dxx0000001', 'preferred_username' => 'admin@lonestar.example']),
            'lonestar.my.salesforce.com/services/data/v62.0/query?q=SELECT%20Id%2C%20FirstName*' => Http::response(['done' => false, 'nextRecordsUrl' => '/services/data/v62.0/query/01gxx-2000', 'records' => [
                ['Id' => '003xx1', 'FirstName' => 'Daniel', 'LastName' => 'Kim', 'Email' => 'daniel@example.com', 'MobilePhone' => '713-555-0170', 'Stage__c' => 'Active buyer', 'Owner' => ['Email' => 'james@example.com'], 'SystemModstamp' => '2026-11-22T10:00:00.000+0000'],
            ]]),
            'lonestar.my.salesforce.com/services/data/v62.0/query/01gxx-2000' => Http::response(['done' => true, 'records' => []]),
            'lonestar.my.salesforce.com/services/data/v62.0/sobjects/Contact/deleted/*' => Http::response(['deletedRecords' => [['id' => '003xx9', 'deletedDate' => '2026-11-21T10:00:00.000+0000']]]),
            'lonestar.my.salesforce.com/services/data/v62.0/sobjects/ContentNote' => Http::response(['id' => '069xx5', 'success' => true]),
            'lonestar.my.salesforce.com/services/data/v62.0/sobjects/ContentDocumentLink' => Http::response(['id' => '06Axx1', 'success' => true]),
            'lonestar.my.salesforce.com/services/data/v62.0/sobjects/Task/00Txx1' => Http::response(null, 204),
            'lonestar.my.salesforce.com/services/data/v62.0/sobjects/Lead/00Qxx1' => Http::response(null, 204),
        ]);
        $driver = new SalesforceDriver('client-1', 'secret-1');

        $url = $driver->authorizationUrl('state-1', 'challenge-1', 'https://reos.test/integrations/salesforce/callback');
        expect($url)->toStartWith('https://login.salesforce.com/services/oauth2/authorize?')->toContain('code_challenge=challenge-1')->toContain('code_challenge_method=S256')->toContain('scope=api+refresh_token');

        $tokens = $driver->exchangeCode('code-1', 'verifier-1', 'https://reos.test/integrations/salesforce/callback');
        expect($tokens->accessToken)->toBe('https://lonestar.my.salesforce.com 00Dxx!session')->and($tokens->refreshToken)->toBe('refresh-1')
            ->and($driver->account($tokens)->externalId)->toBe('00Dxx0000001');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/services/oauth2/token') && $r['code_verifier'] === 'verifier-1' && $r['grant_type'] === 'authorization_code');

        $connection = new Connection(['integration_id' => 'salesforce']);
        $connection->forceFill(['id' => 1, 'meta' => ['field_map' => ['contact_stage' => 'Stage__c', 'leads' => false]]]);
        $mark = (string) json_encode(['marks' => ['Contact' => '2026-11-01T00:00:00Z']]);

        $first = $driver->fetch($connection, $tokens->accessToken, 'people', $mark, null);
        expect($first->done)->toBeFalse()
            ->and($first->records[0])->toMatchArray(['external_id' => '003xx1', 'stage' => 'Active buyer', 'owner_email' => 'james@example.com', 'phones' => ['713-555-0170']]);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'FROM Contact WHERE IsDeleted = false AND SystemModstamp > 2026-11-01T00:00:00Z ORDER BY SystemModstamp, Id')
            && str_contains(urldecode($r->url()), 'Stage__c') && $r->header('Authorization') === ['Bearer 00Dxx!session']);

        $second = $driver->fetch($connection, $tokens->accessToken, 'people', $first->cursor, null);
        expect($second->done)->toBeTrue()->and($second->removed)->toBe(['003xx9'])
            ->and(json_decode((string) $second->cursor, true))->toBe(['marks' => ['Contact' => '2026-11-22T10:00:00.000+0000']]);

        expect($driver->createNote($connection, $tokens->accessToken, '003xx1', "Called back.\nWants a 30-day close."))->toBe('069xx5');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sobjects/ContentDocumentLink') && $r['ContentDocumentId'] === '069xx5' && $r['LinkedEntityId'] === '003xx1');
        $driver->setTaskDone($connection, $tokens->accessToken, '00Txx1', true);
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/sobjects/Task/00Txx1') && $r['Status'] === 'Completed');
        $driver->updatePerson($connection, $tokens->accessToken, '00Qxx1', ['stage' => 'Working - Contacted', 'tags' => ['ignored']]);
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/sobjects/Lead/00Qxx1') && $r->data() === ['Status' => 'Working - Contacted']);
    });

    it('treats a revoked refresh as "reconnect"', function () {
        Http::fake(['login.salesforce.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'expired access/refresh token'], 400)]);

        expect(fn () => (new SalesforceDriver('c', 's'))->refresh('old'))->toThrow(AccessRevoked::class);
    });

    it('builds SOQL without a mark for the first sync, and never with an unsafe field name', function () {
        $map = SalesforceRecords::mapping(['field_map' => ['contact_stage' => 'Stage__c FROM User --', 'lead_stage' => 'Rating']]);

        expect($map)->toBe(['contact_stage' => null, 'lead_stage' => 'Rating', 'leads' => true])
            ->and(SalesforceDriver::soql('Lead', $map, null))->toBe('SELECT Id, FirstName, LastName, Email, Phone, MobilePhone, Rating, Owner.Email, SystemModstamp FROM Lead WHERE IsConverted = false ORDER BY SystemModstamp, Id')
            ->and(SalesforceDriver::soql('Task', $map, CarbonImmutable::parse('2026-11-23T08:00:00-06:00')->toIso8601String()))->toContain('SystemModstamp > 2026-11-23T14:00:00Z');
    });
});
