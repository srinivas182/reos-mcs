<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Deals\Deals;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\Organisation;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\DealDeadline;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->brokerage = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->brokerage)->create(['name' => 'Maria Lopez', 'email' => 'maria@lonestar.test']);
});

function dlDeal(): array
{
    return [
        'property' => '1418 Elm St', 'city' => 'Austin', 'side' => 'buy', 'stage' => 'under_contract', 'price' => 425000, 'commission' => 12750,
        'parties' => [['name' => 'Ana Ruiz', 'role' => 'Buyer', 'email' => 'ana@example.com', 'phone' => null]],
        'dates' => [['kind' => 'option_period', 'due_on' => '2026-11-26'], ['kind' => 'closing', 'due_on' => '2026-12-18']],
    ];
}

describe('deals tracked in REOS (B9.1)', function () {
    it('makes every key date a to-do on that day, on the calendar; moving a date moves it; removing it removes it', function () {
        $made = $this->actingAs($this->maria)->postJson('/api/v1/deals', dlDeal())->assertCreated()->json('data');
        expect($made)->toMatchArray(['property' => '1418 Elm St', 'stage' => 'under_contract', 'read_only' => false, 'source' => 'reos'])
            ->and($made['next'])->toMatchArray(['kind' => 'option_period', 'name' => 'Option period ends', 'due_on' => '2026-11-26', 'days' => 3]);

        $todos = Todo::query()->where('user_id', $this->maria->id)->where('related_type', 'deal')->orderBy('due_date')->get();
        expect($todos->pluck('title')->all())->toBe(['Option period ends · 1418 Elm St', 'Closing · 1418 Elm St'])
            ->and($todos->first()?->due_date?->toDateString())->toBe('2026-11-26')
            ->and($todos->first()?->calendar_event_id)->not->toBeNull()
            ->and($todos->first()?->related_id)->toBe($made['uuid']);

        $moved = dlDeal();
        $moved['dates'] = [['kind' => 'option_period', 'due_on' => '2026-11-28']];
        $this->putJson("/api/v1/deals/{$made['uuid']}", $moved)->assertOk();
        $left = Todo::query()->where('related_type', 'deal')->get();
        expect($left)->toHaveCount(1)->and($left->first()?->due_date?->toDateString())->toBe('2026-11-28');
        expect(Todo::withTrashed()->where('title', 'Closing · 1418 Elm St')->first()?->trashed())->toBeTrue();

        // Dragging works for REOS deals; cancelling takes the to-dos away.
        $this->putJson("/api/v1/deals/{$made['uuid']}/stage", ['stage' => 'cancelled'])->assertOk()->assertJsonPath('data.stage', 'cancelled');
        expect(Todo::query()->where('related_type', 'deal')->count())->toBe(0);
    });

    it('pushes at 72 and 24 hours before 5 pm on the day, once each, and again after a move', function () {
        Notification::fake();
        $uuid = $this->actingAs($this->maria)->postJson('/api/v1/deals', dlDeal())->json('data.uuid');

        // Monday 9 am: Thursday 5 pm is 80 hours away.
        $this->artisan('reos:deals:deadlines')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo(CarbonImmutable::parse('2026-11-23 18:00', 'America/Chicago'));
        $this->artisan('reos:deals:deadlines');
        $this->artisan('reos:deals:deadlines');
        Notification::assertSentToTimes($this->maria, DealDeadline::class, 1);
        Notification::assertSentTo($this->maria, DealDeadline::class, fn (DealDeadline $n): bool => $n->hours === 72 && $n->date === 'Option period ends' && $n->property === '1418 Elm St');

        $this->travelTo(CarbonImmutable::parse('2026-11-25 18:00', 'America/Chicago'));
        $this->artisan('reos:deals:deadlines');
        Notification::assertSentTo($this->maria, DealDeadline::class, fn (DealDeadline $n): bool => $n->hours === 24);
        Notification::assertSentToTimes($this->maria, DealDeadline::class, 2);

        // Moved to Friday: the 24-hour push comes again on Thursday evening.
        $moved = dlDeal();
        $moved['dates'][0]['due_on'] = '2026-11-27';
        $this->putJson("/api/v1/deals/{$uuid}", $moved)->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-26 18:00', 'America/Chicago'));
        $this->artisan('reos:deals:deadlines');
        Notification::assertSentToTimes($this->maria, DealDeadline::class, 3);
    });

    it('shows the board, the pipeline and the alerts; deals from a tool are read-only', function () {
        $this->actingAs($this->maria)->postJson('/api/v1/deals', dlDeal())->assertCreated();
        $listing = dlDeal();
        $listing['property'] = '77 Pecan Ln';
        $listing['side'] = 'sell';
        $listing['stage'] = 'active';
        $listing['commission'] = null;
        $listing['dates'] = [];
        $this->postJson('/api/v1/deals', $listing)->assertCreated();

        $tool = app(Tenancy::class)->run($this->brokerage->id, fn () => app(Deals::class)->receive(Connection::query()->create([
            'integration_id' => 'lone-wolf', 'owner_type' => 'organisation', 'owner_id' => $this->brokerage->id, 'mode' => IntegrationMode::Simulator,
            'external_account_id' => 'lw-1', 'scopes' => [], 'status' => 'active', 'settings' => [], 'cursors' => [], 'meta' => [],
        ]), $this->maria, $this->brokerage->id, [
            'external_id' => 'tx-9', 'external_url' => 'https://transact.lwolf.com/tx/9', 'property' => '9 Oak Ct', 'side' => 'sell', 'stage' => 'under_contract', 'price' => 610000, 'commission' => '18300.00',
            'documents' => [['name' => 'Seller disclosure', 'status' => 'missing', 'url' => null], ['name' => 'Contract', 'status' => 'complete', 'url' => null]],
            'dates' => [['kind' => 'financing', 'due_on' => '2026-12-01', 'external_id' => 'd-1']],
        ]));

        $board = $this->getJson('/api/v1/deals')->assertOk()->json('data');
        expect(array_column($board['under_contract'], 'property'))->toBe(['1418 Elm St', '9 Oak Ct'])->and(array_column($board['active'], 'property'))->toBe(['77 Pecan Ln']);

        $pipeline = $this->getJson('/api/v1/deals/pipeline')->json('data');
        expect($pipeline)->toMatchArray(['under_contract' => 2, 'active' => 1, 'pending_commission' => '31050.00']);

        $alerts = $this->getJson('/api/v1/deals/alerts')->json('data');
        expect(array_map(static fn ($a) => $a['date']['name'], $alerts['deadlines']))->toBe(['Option period ends', 'Financing deadline'])
            ->and($alerts['missing'][0])->toMatchArray(['documents' => ['Seller disclosure']]);

        $this->putJson("/api/v1/deals/{$tool->uuid}/stage", ['stage' => 'closed'])->assertUnprocessable();
        $this->deleteJson("/api/v1/deals/{$tool->uuid}")->assertStatus(422);
        $this->get("/deals?deal={$tool->uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('deals/index')->where('selected.read_only', true)->where('selected.files.0.status', 'missing'));

        // Another agent sees none of it.
        $other = User::factory()->withRole(Role::Agent, $this->brokerage)->create();
        $this->actingAs($other)->getJson("/api/v1/deals/{$tool->uuid}")->assertNotFound();
        expect($this->getJson('/api/v1/deals/pipeline')->json('data.under_contract'))->toBe(0);
        expect(Deal::query()->count())->toBe(3);
    });
});
