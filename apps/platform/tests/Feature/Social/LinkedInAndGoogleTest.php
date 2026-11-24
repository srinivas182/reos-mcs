<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\CatalogueSetting;
use App\Models\Connection;
use App\Models\Licence;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('posts to LinkedIn (profile and page, beta) and Google Business Profile, with insights back (B11.2)', function () {
    $this->travelTo('2026-11-24 15:00:00');
    Storage::fake('content');
    $james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    Licence::query()->create(['user_id' => $james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active, 'sponsor_name' => 'Linda Park Realty']);
    // LinkedIn is a beta: James's brokerage joins it first.
    expect(collect($this->actingAs($james)->getJson('/api/v1/social/accounts')->json('data'))->firstWhere('integration', 'linkedin')['available'])->toBeFalse();
    CatalogueSetting::query()->updateOrCreate(['integration_id' => 'linkedin'], ['beta_organisations' => [app(ActiveRole::class)->for($james)?->organisation->id]]);
    connectOnWeb($this, $james, tool: 'linkedin')->assertRedirect();
    connectOnWeb($this, $james, tool: 'google-business-profile')->assertRedirect();
    $linkedin = Connection::query()->where('integration_id', 'linkedin')->sole();
    $google = Connection::query()->where('integration_id', 'google-business-profile')->sole();

    $accounts = collect($this->actingAs($james)->getJson('/api/v1/social/accounts')->json('data'))->keyBy('integration');
    expect($accounts['linkedin'])->toMatchArray(['built' => true, 'launch' => 'beta'])
        ->and(array_column($accounts['linkedin']['targets'], 'id'))->toBe(['urn:li:person:sim-1', 'urn:li:organization:sim-2'])
        ->and($accounts['google-business-profile']['channels'])->toBe(['google_business' => ['media' => 'optional', 'max_text' => 1500]]);

    $targets = [
        ['connection' => $linkedin->id, 'channel' => 'linkedin', 'id' => 'urn:li:person:sim-1'],
        ['connection' => $linkedin->id, 'channel' => 'linkedin', 'id' => 'urn:li:organization:sim-2'],
        ['connection' => $google->id, 'channel' => 'google_business', 'id' => 'accounts/sim-1/locations/sim-2'],
    ];
    $tooLong = $this->postJson('/api/v1/social/posts/check', ['body' => str_repeat('a', 1600), 'media' => [], 'targets' => $targets, 'when' => 'draft'])->json('data.checks');
    expect(collect($tooLong)->firstWhere('detail', 'google_business')['ok'])->toBeFalse()
        ->and(collect($tooLong)->firstWhere('detail', 'linkedin')['ok'])->toBeTrue();

    $id = $this->postJson('/api/v1/social/posts', ['body' => 'November market update for Austin', 'media' => [], 'link' => 'https://reos.com/agent/james-miller', 'targets' => $targets, 'when' => 'now'])
        ->assertCreated()->assertJsonPath('data.status', 'published')->json('data.id');

    $this->travel(5)->hours();
    $this->artisan('reos:social:insights')->expectsOutput('Read insights for 3 posts.');
    $post = $this->getJson("/api/v1/social/posts/{$id}")->json('data');
    expect(array_keys($post['targets'][0]['insights']))->toBe(['reach', 'likes', 'comments', 'clicks'])
        ->and(array_keys($post['targets'][2]['insights']))->toBe(['views', 'clicks']);
});
