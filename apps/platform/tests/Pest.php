<?php

declare(strict_types=1);

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
