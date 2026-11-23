<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\Connection;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Catalogue\IntegrationCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->linda = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park', 'email' => 'linda.park@demo.reos.test']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'email' => 'james.miller@demo.reos.test']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Maria Garcia', 'email' => 'maria.garcia@demo.reos.test']);
    IntegrationSetting::query()->firstOrCreate(['integration_id' => 'salesforce'], ['mode' => IntegrationMode::Simulator]);
});

/** @return array<string, mixed> */
function toolRow(object $test, string $id): array
{
    $tools = $test->actingAs($test->linda)->get('/organisation/integrations')->assertOk()->viewData('page')['props']['tools'];

    return collect($tools)->firstWhere('id', $id) ?? [];
}

describe('Brokerage integrations (web journey screen 40)', function () {
    it('shows the brokerage\'s own Salesforce first, ready to connect, then the tools for agents', function () {
        $this->actingAs($this->linda)->get('/organisation/integrations')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('organisation/integrations')
            ->where('brokerage.name', 'Lone Star Realty'));

        $tools = collect($this->actingAs($this->linda)->get('/organisation/integrations')->viewData('page')['props']['tools']);
        $firstAgentTool = $tools->search(fn (array $tool): bool => $tool['accounts'] === 'user');
        expect($tools->firstWhere('id', 'salesforce'))->toMatchArray(['accounts' => 'tenant', 'action' => 'connect', 'account' => null])
            ->and($tools->take($firstAgentTool)->pluck('accounts')->unique()->all())->toBe(['tenant'])
            ->and($tools->firstWhere('id', 'follow-up-boss'))->toMatchArray(['accounts' => 'user', 'allowed' => true]);
    });

    it('after connecting, shows sync health in counts only, never the agents\' contacts', function () {
        connectOnWeb($this, $this->linda, tool: 'salesforce')->assertRedirect('/organisation/integrations');

        $row = toolRow($this, 'salesforce');
        expect($row['account'])->toMatchArray(['people' => 3, 'agents' => 2, 'unmatched_owners' => ['assistant@lonestar.example']])
            ->and($row['account']['mapping'])->toBe(['contact_stage' => null, 'lead_stage' => 'Status', 'leads' => true])
            ->and($row['account']['last_synced_at'])->not->toBeNull();

        $page = json_encode($this->actingAs($this->linda)->get('/organisation/integrations')->viewData('page'));
        expect($page)->not->toContain('Daniel')->not->toContain('Grace');
    });

    it('lets the broker hide a tool from agents and allow it again, audited', function () {
        $this->actingAs($this->linda)->put('/organisation/integrations/tools/follow-up-boss', ['allowed' => false])
            ->assertSessionHas('success', __('brokerageIntegrations.hidden'));
        expect(toolRow($this, 'follow-up-boss')['allowed'])->toBeFalse()
            ->and(app(IntegrationCatalogue::class)->hiddenFor($this->lone))->toContain('follow-up-boss');

        $this->actingAs($this->linda)->put('/organisation/integrations/tools/follow-up-boss', ['allowed' => true])->assertSessionHasNoErrors();
        expect(toolRow($this, 'follow-up-boss')['allowed'])->toBeTrue()
            ->and(AuditEvent::query()->whereIn('action', ['organisation.integration_hidden', 'organisation.integration_allowed'])->count())->toBe(2);

        $this->actingAs($this->linda)->put('/organisation/integrations/tools/not-a-tool', ['allowed' => false])->assertNotFound();
    });

    it('saves the stage mapping only to fields Salesforce has, and starts a sync', function () {
        connectOnWeb($this, $this->linda, tool: 'salesforce');
        $connection = Connection::query()->where('integration_id', 'salesforce')->sole();
        $url = "/organisation/integrations/connections/{$connection->id}/mapping";

        $this->actingAs($this->linda)->put($url, ['contact_stage' => 'Nope__c', 'lead_stage' => 'Status', 'leads' => true])
            ->assertSessionHasErrors(['contact_stage' => __('brokerageIntegrations.errors.unknownField')]);
        $this->actingAs($this->linda)->put($url, ['contact_stage' => 'Robert"); DROP', 'lead_stage' => 'Status', 'leads' => true])
            ->assertSessionHasErrors('contact_stage');

        $this->actingAs($this->linda)->put($url, ['contact_stage' => 'Stage__c', 'lead_stage' => 'Status', 'leads' => false])
            ->assertSessionHas('success', __('brokerageIntegrations.mapped'));
        expect($connection->refresh()->meta['field_map'])->toBe(['contact_stage' => 'Stage__c', 'lead_stage' => 'Status', 'leads' => false])
            ->and(AuditEvent::query()->where('action', 'organisation.crm_mapping_updated')->exists())->toBeTrue();
    });

    it('is for brokerage admins of brokerages only', function () {
        $this->actingAs($this->james)->get('/organisation/integrations')->assertForbidden();

        $school = Organisation::factory()->ofType(OrganisationType::School)->create();
        $paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $school)->create();
        $this->actingAs($paul)->get('/organisation/integrations')->assertNotFound();

        // Another brokerage's connection can't be remapped.
        connectOnWeb($this, $this->linda, tool: 'salesforce');
        $connection = Connection::query()->where('integration_id', 'salesforce')->sole();
        $gulf = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
        $other = User::factory()->withTwoFactor()->withRole(Role::Broker, $gulf)->create();
        $this->actingAs($other)->put("/organisation/integrations/connections/{$connection->id}/mapping", ['lead_stage' => 'Status', 'leads' => true])->assertNotFound();
    });
});
