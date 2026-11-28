<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Compliance\ComplianceEngine;
use App\Compliance\TexasAdRules;
use App\Compliance\Wording;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Models\Connection;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use App\Studio\StudioRenderer;
use App\Studio\Video\VideoFrames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-27 15:00:00');
    Storage::fake('content');
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    Licence::query()->create([
        'user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active,
        'holder_name' => 'James Miller', 'sponsor_name' => 'Linda Park Realty', 'verified_by' => 'trec-licence', 'verified_at' => now(),
    ]);
});

function checkPost(object $test, User $agent, string $body): array
{
    return $test->actingAs($agent)->postJson('/api/v1/social/posts/check', ['body' => $body, 'media' => [], 'link' => null, 'targets' => [], 'when' => 'draft'])->assertOk()->json('data');
}

describe('the compliance engine (B13.1)', function () {
    it('blocks a post without the broker\'s name (the done-when)', function () {
        $nobroker = User::factory()->create(['name' => 'Sam Lee']);
        connectOnWeb($this, $nobroker, tool: 'meta')->assertRedirect();
        $page = ['connection' => Connection::query()->where('integration_id', 'meta')->sole()->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];

        $check = checkPost($this, $nobroker, 'Open house Saturday at 88 Oak Ridge Dr.');
        $broker = collect($check['checks'])->firstWhere('code', 'broker');
        expect($check['ok'])->toBeFalse()->and($broker)->toMatchArray(['ok' => false, 'citation' => '22 TAC §535.155(c)']);
        $this->postJson('/api/v1/social/posts', ['body' => 'Open house Saturday at 88 Oak Ridge Dr.', 'media' => [], 'targets' => [$page], 'when' => 'now'])->assertUnprocessable();

        // With a brokerage, the attribution carries its name and the check passes.
        $check = checkPost($this, $this->james, 'Open house Saturday at 88 Oak Ridge Dr.');
        expect($check['attribution'])->toContain((string) app(ComplianceEngine::class)->brokerName($this->james))->and(collect($check['checks'])->firstWhere('code', 'broker')['ok'])->toBeTrue();
    });

    it('checks team names, titles and claims with their citations', function () {
        $team = collect(checkPost($this, $this->james, 'The Miller Realty Team has a new listing in Katy!')['checks'])->firstWhere('code', 'team_name');
        expect($team)->toMatchArray(['ok' => false, 'detail' => 'The Miller Realty Team', 'citation' => '22 TAC §535.154']);
        // A team name with the broker's name (on the attribution) and no brokerage words is fine.
        expect(collect(checkPost($this, $this->james, 'The Miller Team has a new listing in Katy!')['checks'])->firstWhere('code', 'team_name'))->toBeNull();

        $checks = collect(checkPost($this, $this->james, "As your broker, I'm offering a guaranteed sale.")['checks'])->keyBy('code');
        expect($checks['title']['ok'])->toBeFalse()->and($checks['claims'])->toMatchArray(['ok' => false, 'citation' => '22 TAC §535.155(d)']);
    });

    it('never flags place and room names, and asks for a second look at unclear wording', function () {
        $text = 'Big family room, two blocks from Indian Wells Park on Christian Street.';
        expect(Wording::find($text))->toBe([]);
        expect(collect(checkPost($this, $this->james, $text)['checks'])->firstWhere('code', 'fair_housing')['ok'])->toBeTrue();

        $fixed = $this->postJson('/api/v1/social/posts/fix', ['body' => 'Perfect for families: a big family room near Indian Wells.'])->assertOk()->json('data.body');
        expect($fixed)->toBe('roomy layout: a big family room near Indian Wells.');

        // "Walking distance" depends on how it's used. Without Claude configured, it's a warning, not a block.
        $check = checkPost($this, $this->james, 'Walking distance to the park and the farmers market.');
        expect(collect($check['checks'])->firstWhere('code', 'fair_housing')['ok'])->toBeTrue()->and($check['review'])->toBe(['walking distance']);
        // "Walking distance to church" is a known problem.
        expect(collect(checkPost($this, $this->james, 'Walking distance to church.')['checks'])->firstWhere('code', 'fair_housing')['ok'])->toBeFalse();
    });

    it('checks emails and texts to contacts, and fixes them automatically', function () {
        $issues = $this->actingAs($this->james)->postJson('/api/v1/compliance/check', ['channel' => 'text', 'text' => 'Hi Ana! Guaranteed to sell fast. Great for kids, no section 8.'])->assertOk()->json('data');
        expect($issues['ok'])->toBeFalse()
            ->and(array_column($issues['issues'], 'code'))->toEqualCanonicalizing(['claims', 'fair_housing', 'fair_housing'])
            // A one-to-one message isn't an ad, so the broker's name isn't required.
            ->and(array_column($issues['issues'], 'code'))->not->toContain('broker');

        $fixed = $this->postJson('/api/v1/compliance/fix', ['channel' => 'text', 'text' => 'Hi Ana! Great for kids, with a big family room.'])->assertOk()->json('data');
        expect($fixed)->toMatchArray(['text' => 'Hi Ana! big backyard, with a big family room.', 'ok' => true]);
        $this->postJson('/api/v1/compliance/check', ['channel' => 'fax', 'text' => 'x'])->assertUnprocessable();
    });

    it('keeps the public page compliant before it is published', function () {
        $this->actingAs($this->james)->putJson('/api/v1/listings/page', ['published' => true, 'bio' => 'Katy homes, perfect for families.'])->assertUnprocessable()->assertJsonValidationErrors('bio');
        $this->putJson('/api/v1/listings/page', ['published' => true, 'bio' => 'Katy homes, twelve years.'])->assertOk()->assertJsonPath('data.published', true);
        $this->get('/agent/james-miller')->assertOk()->assertSee((string) app(ComplianceEngine::class)->brokerName($this->james));

        // An agent on a team with no brokerage and no sponsoring broker can't publish.
        $nobroker = User::factory()->withRole(Role::Agent, Organisation::factory()->create(['type' => OrganisationType::Team, 'name' => 'Bayou Home Group']))->create(['name' => 'Sam Lee']);
        $this->actingAs($nobroker)->putJson('/api/v1/listings/page', ['published' => true, 'bio' => null])->assertUnprocessable();
    });

    it('never cuts the broker\'s name off a design or a video', function () {
        $long = 'Christopher Alexander Montgomery-Hernandez · Lone Star Realty of Greater Houston';
        expect(StudioRenderer::attributionLines($long)[1])->toBe('Lone Star Realty of Greater Houston')
            // On a video the broker's name may take two lines; it's never shortened.
            ->and(implode(' ', array_slice(VideoFrames::attributionLines($long), 1)))->toBe('Lone Star Realty of Greater Houston');
        expect(VideoFrames::attributionLines('James Miller · Linda Park Realty'))->toBe(['James Miller · Linda Park Realty']);

        $engine = app(ComplianceEngine::class);
        $cut = $engine->check($this->james, ['channel' => 'video', 'text' => 'Just listed', 'attribution' => 'James Miller · Linda Park Realty', 'layout' => ['rendered' => 'James Miller · Linda Park…', 'broker_size' => 40, 'largest_contact' => 40]]);
        expect(array_column($cut['issues'], 'code'))->toContain('broker_size');
        $small = $engine->check($this->james, ['channel' => 'video', 'text' => 'Just listed', 'attribution' => 'James Miller · Linda Park Realty', 'layout' => ['rendered' => 'James Miller · Linda Park Realty', 'broker_size' => 18, 'largest_contact' => 40]]);
        expect(array_column($small['issues'], 'code'))->toContain('broker_size')
            ->and(TexasAdRules::rule('broker_size')->citation)->toBe('22 TAC §535.155(a)(2)');
    });
});
