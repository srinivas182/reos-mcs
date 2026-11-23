<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Stores\ContactStore;
use App\Crm\Contacts;
use App\Enums\OrganisationType;
use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\ContactLink;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Identity\AccountDeletion;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    $this->contacts = app(Contacts::class);
});

/** @param  array<string, mixed>  $record */
function crmRecord(object $test, string $source, array $record): Contact
{
    return $test->contacts->receive(Connection::OWNER_USER, $test->james->id, $source, null, $record);
}

function jamesContacts(object $test): Collection
{
    return Contact::query()->where('owner_type', 'user')->where('owner_id', $test->james->id)->with('links')->orderBy('id')->get();
}

describe('one person, all sources (the B7.1 done-when)', function () {
    it('merges the same people from a CRM and the phone without duplicates, keeping both links', function () {
        crmRecord($this, 'follow-up-boss', ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria.lopez@example.com'], 'phones' => ['(832) 555-0123']]);
        crmRecord($this, 'follow-up-boss', ['external_id' => '1002', 'first_name' => 'David', 'last_name' => 'Chen', 'emails' => ['dchen@example.com']]);

        $this->actingAs($this->james);
        $this->postJson('/api/v1/contacts/phone', ['contacts' => [
            ['id' => 'ph-1', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'phones' => ['+1 832 555 0123'], 'birthday' => '1988-04-09'],
            ['id' => 'ph-2', 'first_name' => 'David', 'last_name' => 'Chen', 'emails' => ['DChen@Example.com'], 'phones' => ['346-555-0142']],
            ['id' => 'ph-3', 'first_name' => 'Angela', 'last_name' => 'Brooks', 'emails' => ['angela@example.com']],
        ]])->assertOk()->assertJson(['received' => 3, 'contacts' => 3]);

        // The same sync again changes nothing.
        crmRecord($this, 'follow-up-boss', ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria.lopez@example.com'], 'phones' => ['(832) 555-0123']]);

        $contacts = jamesContacts($this);
        expect($contacts)->toHaveCount(3)
            ->and($contacts[0]->links->pluck('source')->sort()->values()->all())->toBe(['follow-up-boss', 'phone'])
            ->and($contacts[0]->birthday?->toDateString())->toBe('1988-04-09')
            ->and(array_column($contacts[1]->phones, 'value'))->toBe(['+13465550142'])
            ->and(array_column($contacts[1]->emails, 'value'))->toBe(['dchen@example.com']);

        $this->getJson('/api/v1/contacts')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonFragment(['sources' => [
                ['source' => 'follow-up-boss', 'name' => 'Follow Up Boss', 'synced_at' => $contacts[0]->links->firstWhere('source', 'follow-up-boss')->synced_at?->toIso8601String(), 'campaign' => null, 'campaign_status' => null],
                ['source' => 'phone', 'name' => 'Phone contacts', 'synced_at' => $contacts[0]->links->firstWhere('source', 'phone')->synced_at?->toIso8601String(), 'campaign' => null, 'campaign_status' => null],
            ]]);
    });

    it('never merges on a name alone, but suggests it', function () {
        crmRecord($this, 'follow-up-boss', ['external_id' => '1004', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'phones' => ['281-555-0101']]);
        crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-3', 'first_name' => 'mike', 'last_name' => 'johnson', 'phones' => ['979-555-0177']]);

        expect(jamesContacts($this))->toHaveCount(2);
        $this->actingAs($this->james);
        $suggestion = $this->getJson('/api/v1/contacts/activity')->assertOk()->assertJsonCount(1, 'suggestions')->json('suggestions.0');

        $this->postJson("/api/v1/contacts/suggestions/{$suggestion['id']}/dismiss")->assertNoContent();
        $this->getJson('/api/v1/contacts/activity')->assertJsonCount(0, 'suggestions');
    });
});

describe('conflicts and echoes', function () {
    it('lets the newest change win, field by field, and keeps the losing value in Sync activity', function () {
        crmRecord($this, 'follow-up-boss', ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria@example.com'], 'stage' => 'Lead', 'updated_at' => '2026-11-10T10:00:00Z']);
        crmRecord($this, 'boldtrail', ['external_id' => 'kv-9', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria@example.com'], 'stage' => 'Active client', 'updated_at' => '2026-11-20T10:00:00Z']);
        // An older change arriving late doesn't win.
        crmRecord($this, 'follow-up-boss', ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria@example.com'], 'stage' => 'Nurture', 'updated_at' => '2026-11-15T10:00:00Z']);

        $contact = jamesContacts($this)->sole();
        expect($contact->stage)->toBe('Active client')
            ->and(ContactActivity::query()->where('kind', 'changed')->sole()->details)->toBe(['field' => 'stage', 'value' => 'Active client', 'previous' => 'Lead', 'previous_source' => 'follow-up-boss'])
            ->and(ContactActivity::query()->where('kind', 'kept')->sole()->details)->toBe(['field' => 'stage', 'value' => 'Active client', 'previous' => 'Nurture']);

        $this->actingAs($this->james);
        $texts = collect($this->getJson('/api/v1/contacts/activity')->json('data'))->pluck('text');
        expect($texts)->toContain('kvCORE / BoldTrail changed the stage of Maria Lopez to “Active client”. The earlier value from Follow Up Boss was “Lead”.')
            ->and($texts)->toContain('Follow Up Boss had an older stage for Maria Lopez (“Nurture”), so the newer “Active client” was kept.');
    })->skip(fn () => app(Catalogue::class)->find('boldtrail') === null, 'BoldTrail is not in the register');

    it('never reads REOS\'s own write back as a change', function () {
        $contact = crmRecord($this, 'follow-up-boss', ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'stage' => 'Lead']);
        $link = $contact->links()->sole();
        $written = ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'stage' => 'Under contract'];
        $contact->forceFill(['stage' => 'Under contract'])->save();
        $this->contacts->wrote($link, $written);
        $before = ContactActivity::query()->count();

        crmRecord($this, 'follow-up-boss', [...$written, 'updated_at' => now()->addMinute()->toIso8601String()]);

        expect(ContactActivity::query()->count())->toBe($before)
            ->and($contact->refresh()->stage)->toBe('Under contract');
    });

    it('keeps a contact removed at its source, and says so', function () {
        crmRecord($this, 'follow-up-boss', ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria@example.com']]);
        crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-1', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria@example.com']]);

        $this->contacts->removed('user', $this->james->id, 'follow-up-boss', ['1001']);

        expect(jamesContacts($this)->sole()->links->pluck('source')->all())->toBe(['phone'])
            ->and(ContactActivity::query()->where('kind', 'removed')->exists())->toBeTrue();
    });
});

describe('merge and split', function () {
    it('merges two contacts, keeps every link, and splits them again; both audited', function () {
        $a = crmRecord($this, 'follow-up-boss', ['external_id' => '1004', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'phones' => ['281-555-0101'], 'tags' => ['Buyer']]);
        $b = crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-3', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'phones' => ['979-555-0177'], 'tags' => ['Golf']]);
        $this->actingAs($this->james);

        $this->postJson("/api/v1/contacts/{$a->uuid}/merge", ['other' => $b->uuid])->assertOk()
            ->assertJsonPath('data.phones', ['+12815550101', '+19795550177'])
            ->assertJsonPath('data.tags', ['Buyer', 'Golf']);
        expect(jamesContacts($this))->toHaveCount(1)
            ->and(jamesContacts($this)->sole()->links)->toHaveCount(2);

        // A later phone sync follows the merge instead of making a duplicate.
        crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-3', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'phones' => ['979-555-0177']]);
        expect(jamesContacts($this))->toHaveCount(1);

        $this->postJson("/api/v1/contacts/{$b->uuid}/split")->assertOk()->assertJsonPath('data.phones', ['+19795550177']);
        $after = jamesContacts($this);
        expect($after)->toHaveCount(2)
            ->and(array_column($after[0]->phones, 'value'))->toBe(['+12815550101'])
            ->and($after[0]->links->pluck('source')->all())->toBe(['follow-up-boss'])
            ->and($after[1]->links->pluck('source')->all())->toBe(['phone'])
            ->and(AuditEvent::query()->where('action', 'contact.merged')->where('actor_id', $this->james->id)->exists())->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'contact.split')->exists())->toBeTrue();
    });

    it('keeps people\'s contacts to themselves', function () {
        $a = crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-1', 'first_name' => 'Maria', 'last_name' => 'Lopez']);
        $linda = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($linda);

        $this->getJson('/api/v1/contacts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/contacts/activity')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/contacts/{$a->uuid}/merge", ['other' => $a->uuid])->assertNotFound();
    });
});

describe('sources', function () {
    it('removes phone contacts nobody else knows when phone contacts are turned off', function () {
        crmRecord($this, 'follow-up-boss', ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria@example.com']]);
        crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-1', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria@example.com']]);
        crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-2', 'first_name' => 'Angela', 'last_name' => 'Brooks']);
        $this->actingAs($this->james);

        $this->deleteJson('/api/v1/contacts/phone')->assertNoContent();

        expect(jamesContacts($this)->pluck('first_name')->all())->toBe(['Maria'])
            ->and(ContactLink::query()->where('source', 'phone')->exists())->toBeFalse();
    });

    it('saves a CRM stream through the connections engine\'s store, for a brokerage under row-level security', function () {
        $brokerage = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
        $connection = app(Tenancy::class)->run($brokerage->id, fn () => Connection::query()->create([
            'integration_id' => 'follow-up-boss', 'owner_type' => 'organisation', 'owner_id' => $brokerage->id, 'mode' => IntegrationMode::Simulator,
            'external_account_id' => 'fub-1', 'scopes' => [], 'status' => 'active', 'settings' => [], 'cursors' => [], 'meta' => [],
        ]));
        $store = app(ContactStore::class);

        app(Tenancy::class)->run($brokerage->id, function () use ($store, $connection, $brokerage) {
            expect($store->upsert($connection, [
                ['external_id' => 501, 'first_name' => 'Priya', 'last_name' => 'Shah', 'emails' => ['priya@example.com'], 'tags' => ['Seller']],
                ['first_name' => 'No id'],
            ]))->toBe(1)
                ->and($store->count($connection))->toBe(1)
                ->and(Contact::query()->sole()->organisation_id)->toBe($brokerage->id);
            $store->forget($connection);
            expect(Contact::query()->count())->toBe(0);
        });
    });
});

it('deletes a person\'s own contacts with their account', function () {
    crmRecord($this, Contacts::PHONE, ['external_id' => 'ph-1', 'first_name' => 'Maria', 'last_name' => 'Lopez']);
    $this->james->forceFill(['deletion_requested_at' => now()->subDays(40)])->save();

    app(AccountDeletion::class)->purge();

    expect(Contact::withTrashed()->withoutGlobalScopes()->count())->toBe(0);
});
