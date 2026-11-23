<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\Licence;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
| Pest bootstrap. Feature tests run on the Laravel test case; the existing
| PHPUnit-style classes keep working alongside Pest tests.
*/

pest()->extend(TestCase::class)->in('Feature');

// Connections (B3.1, B3.2): shared by the engine and the website's page tests.
const CALENDAR = 'google-calendar';

/** Signs in on the website, then walks the demo sign-in to the callback. Returns the callback response. */
function connectOnWeb(object $test, User $user, bool $allow = true, ?callable $tamper = null, string $tool = CALENDAR): TestResponse
{
    $start = $test->actingAs($user)->get('/connections/'.$tool.'/connect?history=12m');
    $start->assertRedirect();
    $simulator = (string) $start->headers->get('Location');
    expect($simulator)->toContain('/connections/simulator/'.$tool);

    parse_str((string) parse_url($simulator, PHP_URL_QUERY), $query);
    $test->get($simulator)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('connections/simulator')
        ->where('account', $user->email));

    $decision = [...$query, 'allow' => $allow ? '1' : '0'];
    if ($tamper !== null) {
        $decision = $tamper($decision);
    }

    $answer = $test->post('/connections/simulator/'.$tool, $decision);
    $answer->assertRedirect();

    return $test->get((string) $answer->headers->get('Location'));
}

// MLS feeds (B10.1, B10.2): shared by the feed and listing-service tests.
/** An agent with a Texas licence, as A5.1 records it. */
function mlsAgent(string $name, string $number): User
{
    $user = User::factory()->withRole(Role::Agent)->create(['name' => $name]);
    Licence::query()->create([
        'user_id' => $user->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => $number, 'status' => LicenceStatus::Active,
        'holder_name' => $name, 'verified_by' => 'simulator', 'verified_at' => now(),
    ]);

    return $user;
}
