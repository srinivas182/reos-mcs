<?php

declare(strict_types=1);

use App\Agents\AgentBriefs;
use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\AgentProfile;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Licence;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\MorningBriefReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tuesday 24 November 2026, 7:00 in Texas.
    $this->travelTo('2026-11-24 13:00:00');
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    Licence::query()->create([
        'user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active,
        'holder_name' => 'James Miller', 'licensed_on' => '2026-04-01', 'verified_by' => 'trec-licence', 'verified_at' => now(),
    ]);
    $this->actingAs($this->james)->putJson('/api/v1/agent/mode', ['goal' => 'launch', 'reason' => 'setup'])->assertOk();
});

describe('the morning brief (A11.1c)', function () {
    it('reads to-dos, appointments, deadlines, new leads and CE, then who to call first', function () {
        $ana = Contact::query()->create(['uuid' => (string) Str::uuid7(), 'owner_type' => 'user', 'owner_id' => $this->james->id, 'first_name' => 'Ana', 'last_name' => 'Garcia', 'phones' => [['value' => '+1 512 555 0142', 'source' => 'zillow-leads']]]);
        Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Send the Nguyens comps', 'due_date' => '2026-11-23']);
        Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Call the lender', 'due_date' => '2026-11-24']);
        CalendarEvent::query()->create(['user_id' => $this->james->id, 'source' => 'reos', 'kind' => 'showing', 'title' => 'Showing at Oak Ridge', 'starts_at' => '2026-11-24 16:00:00', 'ends_at' => '2026-11-24 17:00:00', 'all_day' => false, 'status' => 'confirmed']);
        $deal = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'reos', 'property' => 'Oak Ridge', 'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);
        $option = Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Option period ends', 'due_date' => '2026-11-26', 'source' => 'deal']);
        DealDate::query()->create(['deal_id' => $deal->id, 'kind' => 'option_period', 'due_on' => '2026-11-26', 'todo_id' => $option->id]);

        $brief = $this->getJson('/api/v1/agent/brief')->assertOk()->json('data');

        expect($brief['date'])->toBe('2026-11-24')
            ->and(array_column($brief['sections'], 'key'))->toBe(['todos', 'appointments', 'deadlines', 'leads', 'ce'])
            ->and($brief['sections'][0]['text'])->toBe('2 to-dos, starting with Send the Nguyens comps. One is overdue.')
            ->and($brief['sections'][1]['text'])->toBe('One appointment: Showing at Oak Ridge at 10:00 AM.')
            ->and($brief['sections'][2]['text'])->toBe('One deal deadline coming up: Option period ends, Oak Ridge.')
            ->and($brief['sections'][3]['text'])->toBe('One new lead: Ana Garcia.')
            ->and($brief['sections'][4]['text'])->toStartWith('CE: 0 of ')
            ->and($brief['first_call'])->toBe(['name' => 'Ana Garcia', 'phone' => '+1 512 555 0142', 'contact' => $ana->uuid])
            ->and(array_column($brief['commands'], 'label'))->toBe(['Call Ana Garcia', 'Open the Oak Ridge deal', 'Open Today'])
            ->and($brief['commands'][1]['deal'])->toBe($deal->uuid)
            ->and($brief['script'][0])->toBe('Good morning, James.')
            ->and($brief['script'])->toContain('Call Ana Garcia first.')
            ->and(end($brief['script']))->toBe('That’s your day. Say a command, or open Today.');
    });

    it('reads in Spanish and only the sections chosen', function () {
        $this->james->forceFill(['locale' => 'es'])->save();
        $this->putJson('/api/v1/agent/brief/settings', ['enabled' => true, 'at' => '06:30', 'timezone' => 'America/Chicago', 'sections' => ['appointments', 'leads']])->assertOk()
            ->assertJsonPath('data', ['enabled' => true, 'at' => '06:30', 'timezone' => 'America/Chicago', 'sections' => ['appointments', 'leads']]);

        $brief = $this->withHeader('Accept-Language', 'es')->getJson('/api/v1/agent/brief')->assertOk()->json('data');
        expect(array_column($brief['sections'], 'text'))->toBe(['No tiene citas hoy.', 'No hay clientes potenciales nuevos desde ayer.'])
            ->and($brief['script'][0])->toBe('Buenos días, James.')
            ->and($brief['first_call'])->toBeNull();

        $this->putJson('/api/v1/agent/brief/settings', ['enabled' => true, 'at' => '7am', 'timezone' => 'Mars/Olympus', 'sections' => ['weather']])->assertUnprocessable()
            ->assertJsonValidationErrors(['at', 'timezone', 'sections.0']);
    });

    it('sends "ready" once a day at the agent\'s time in their zone, with nothing private', function () {
        Notification::fake();
        $this->putJson('/api/v1/agent/brief/settings', ['enabled' => true, 'at' => '07:30', 'timezone' => 'America/Chicago', 'sections' => AgentBriefs::SECTIONS])->assertOk();
        $linda = User::factory()->withRole(Role::Agent)->create();
        AgentProfile::query()->create(['user_id' => $linda->id, 'goal' => 'grow', 'brief_at' => '07:30', 'brief_timezone' => 'America/Los_Angeles']);
        $off = User::factory()->withRole(Role::Agent)->create();
        AgentProfile::query()->create(['user_id' => $off->id, 'goal' => 'grow', 'brief_enabled' => false, 'brief_at' => '07:00']);

        $this->artisan('reos:agents:briefs')->expectsOutput('Sent 0 morning briefs.')->assertSuccessful();
        $this->travelTo('2026-11-24 13:35:00');
        $this->artisan('reos:agents:briefs')->expectsOutput('Sent 1 morning briefs.');
        $this->artisan('reos:agents:briefs')->expectsOutput('Sent 0 morning briefs.');
        Notification::assertSentToTimes($this->james, MorningBriefReady::class, 1);
        Notification::assertNotSentTo([$linda, $off], MorningBriefReady::class);

        // Two hours late is too late: Linda's 7:30 in California passes while the job is down.
        $this->travelTo('2026-11-24 18:00:00');
        $this->artisan('reos:agents:briefs')->expectsOutput('Sent 0 morning briefs.');
        // Tomorrow morning in California.
        $this->travelTo('2026-11-25 15:31:00');
        $this->artisan('reos:agents:briefs')->expectsOutput('Sent 1 morning briefs.');
        Notification::assertSentToTimes($linda, MorningBriefReady::class, 1);

        $push = (new MorningBriefReady)->push($this->james);
        expect($push)->toBe(['title' => 'Your morning brief is ready', 'body' => null, 'path' => '/agent/brief']);
    });

    it('shows the brief and its settings on the web, and keeps it to agents', function () {
        $this->get('/agent/brief')->assertInertia(fn (Assert $page) => $page->component('agent/brief')
            ->where('settings.at', '07:00')->where('sections', AgentBriefs::SECTIONS)->has('brief.script'));
        $this->put('/agent/brief/settings', ['enabled' => false, 'at' => '08:15', 'timezone' => 'America/New_York', 'sections' => ['todos']])->assertOk()->assertJsonPath('data.enabled', false);
        expect(AgentProfile::query()->where('user_id', $this->james->id)->sole())->brief_at->toBe('08:15')->brief_sections->toBe(['todos']);

        $candidate = User::factory()->withRole(Role::Candidate)->create();
        $this->actingAs($candidate)->getJson('/api/v1/agent/brief')->assertNotFound();
    });
});
