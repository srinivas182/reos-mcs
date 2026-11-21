<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Notifications\PrivacyRequestUpdated;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\AccountDeletion;
use App\Services\Privacy\PrivacyRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Privacy requests (TDPSA) and Compliance and audit (A4.3b, web screen 71). */
function complianceOps(): User
{
    return User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
}

function asComplianceAdmin(object $test, User $ops): object
{
    return $test->actingAs($ops)->withSession(['auth.password_confirmed_at' => time()]);
}

beforeEach(function () {
    Notification::fake();
    Storage::fake();
});

describe('see my data', function () {
    it('prepares the file at once and sends a 7-day link only its owner can use', function () {
        $rosa = User::factory()->withRole(Role::Agent)->create(['name' => 'Rosa Lima', 'email' => 'rosa@example.com', 'password' => 'secret-password-1']);

        $this->actingAs($rosa)->from('/settings/profile')->post('/settings/privacy', ['kind' => 'access'])->assertRedirect('/settings/profile');

        $request = PrivacyRequest::query()->sole();
        expect($request->status)->toBe(PrivacyRequest::DONE)->and($request->export_path)->not->toBeNull();
        $file = (string) Storage::get((string) $request->export_path);
        expect(json_decode($file, true)['profile']['email'])->toBe('rosa@example.com')
            ->and($file)->not->toContain('secret-password-1')->not->toContain('$2y$')->not->toContain('two_factor_secret');

        $link = '';
        Notification::assertSentTo($rosa, PrivacyRequestUpdated::class, function (PrivacyRequestUpdated $n) use (&$link) {
            $link = (string) $n->downloadLink;

            return $n->downloadLink !== null;
        });

        $this->actingAs($rosa)->get($link)->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->actingAs(User::factory()->create())->get($link)->assertNotFound();
        $this->actingAs($rosa)->get("/settings/privacy/{$request->id}/download")->assertForbidden();

        $this->actingAs($rosa)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
            ->where('privacyRequests.0.kind', 'access')
            ->where('privacyRequests.0.status', 'done')
            ->where('privacyRequests.0.download', fn ($url) => is_string($url) && str_contains($url, 'signature=')));

        // After 7 days the link expires and the file is deleted.
        $this->travel(8)->days();
        $this->actingAs($rosa)->get($link)->assertForbidden();
        Artisan::call('reos:privacy:prune');
        Storage::assertMissing((string) $request->export_path);
    });
});

describe('correct my data, answered on screen 71', function () {
    it('tracks the 45-day clock, extends once with a reason, and tells the person', function () {
        $rosa = User::factory()->create(['name' => 'Rosa Lima', 'email' => 'rosa@example.com']);
        $ops = complianceOps();

        $this->actingAs($rosa)->post('/settings/privacy', ['kind' => 'correct'])->assertSessionHasErrors('details');
        $this->actingAs($rosa)->post('/settings/privacy', ['kind' => 'correct', 'details' => 'My last name is Lima, not Lim.'])->assertSessionHasNoErrors();
        $this->actingAs($rosa)->post('/settings/privacy', ['kind' => 'correct', 'details' => 'Again'])->assertSessionHasErrors(['kind' => __('privacy.errors.waiting')]);

        $request = PrivacyRequest::query()->sole();
        expect($request->due_at->isSameDay(now()->addDays(45)))->toBeTrue();
        Notification::assertSentTo($rosa, PrivacyRequestUpdated::class);

        asComplianceAdmin($this, $ops)->get('/admin/compliance')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/compliance/index')
            ->where('privacy.0.kind', 'correct')
            ->where('privacy.0.person', 'Rosa Lima')
            ->where('privacy.0.status', 'open')
            ->where('limitDays', 45));

        asComplianceAdmin($this, $ops)->patch("/admin/compliance/privacy/{$request->id}", ['action' => 'start'])->assertRedirect();
        asComplianceAdmin($this, $ops)->patch("/admin/compliance/privacy/{$request->id}", ['action' => 'extend'])->assertSessionHasErrors('note');
        asComplianceAdmin($this, $ops)->patch("/admin/compliance/privacy/{$request->id}", ['action' => 'extend', 'note' => 'Checking with your brokerage.'])->assertSessionHasNoErrors();
        asComplianceAdmin($this, $ops)->patch("/admin/compliance/privacy/{$request->id}", ['action' => 'extend', 'note' => 'Again'])->assertSessionHasErrors('request');
        expect($request->fresh()?->due_at->isSameDay(now()->addDays(90)))->toBeTrue();

        asComplianceAdmin($this, $ops)->patch("/admin/compliance/privacy/{$request->id}", ['action' => 'done', 'note' => 'Updated to Rosa Lima.'])->assertSessionHasNoErrors();
        expect($request->fresh())->status->toBe(PrivacyRequest::DONE)->handled_by->toBe($ops->id);
        asComplianceAdmin($this, $ops)->patch("/admin/compliance/privacy/{$request->id}", ['action' => 'decline', 'note' => 'x'])->assertSessionHasErrors('request');

        $updates = AuditEvent::query()->where('action', 'privacy.updated')->get();
        expect($updates)->toHaveCount(3)
            ->and($updates->last()?->properties['before']['status'])->toBe('in_progress')
            ->and($updates->last()?->properties['after']['status'])->toBe('done');
        Notification::assertSentToTimes($rosa, PrivacyRequestUpdated::class, 3);
    });

    it('shows overdue requests first', function () {
        $privacy = app(PrivacyRequests::class);
        $privacy->submit(User::factory()->create(['name' => 'Newer']), PrivacyRequest::CORRECT, 'a');
        $this->travel(-50)->days();
        $privacy->submit(User::factory()->create(['name' => 'Older']), PrivacyRequest::CORRECT, 'b');
        $this->travelBack();

        asComplianceAdmin($this, complianceOps())->get('/admin/compliance')->assertInertia(fn (Assert $page) => $page
            ->where('privacy.0.person', 'Older')
            ->where('privacy.0.overdue', true)
            ->where('privacy.1.overdue', false));
    });
});

describe('delete my account', function () {
    it('is on hold for 30 days, withdrawn on sign-in, done and anonymised once deleted', function () {
        $rosa = User::factory()->create(['name' => 'Rosa Lima', 'email' => 'rosa@example.com']);
        $pat = User::factory()->create(['name' => 'Pat Hill']);
        $deletion = app(AccountDeletion::class);

        $deletion->request($rosa);
        $deletion->request($pat);
        $deletion->cancel($pat);

        expect(PrivacyRequest::query()->where('user_id', $pat->id)->sole()->status)->toBe(PrivacyRequest::WITHDRAWN)
            ->and(PrivacyRequest::query()->where('user_id', $rosa->id)->sole()->status)->toBe(PrivacyRequest::HOLD);

        $this->travel(31)->days();
        $deletion->purge();

        $done = PrivacyRequest::query()->where('kind', PrivacyRequest::DELETE)->where('status', PrivacyRequest::DONE)->sole();
        expect($done)->user_id->toBeNull()->name->toBeNull()->email->toBeNull();
    });
});

describe('the audit log (screen 71)', function () {
    it('filters by area, tenant, person and dates, a page at a time', function () {
        $ops = complianceOps();
        $lone = Organisation::factory()->create(['name' => 'Lone Star Realty']);
        $linda = User::factory()->create(['name' => 'Linda Park', 'email' => 'linda@lonestar.example']);
        $audit = app(AuditLogger::class);
        $audit->record('brand.updated', 'brand', '1', ['before' => ['primary' => null], 'after' => ['primary' => '#1E3A8A']], $linda, $lone->id);
        $audit->record('tenant.status_changed', 'organisation', (string) $lone->id, ['from' => 'active', 'to' => 'suspended'], $ops, $lone->id);
        $this->travel(-10)->days();
        $audit->record('state.status_changed', 'state', 'LA', ['from' => 'coming_soon', 'to' => 'in_preparation'], $ops);
        $this->travelBack();

        asComplianceAdmin($this, $ops)->get('/admin/compliance?group=brand')->assertInertia(fn (Assert $page) => $page
            ->has('audit.rows', 1)
            ->where('audit.rows.0.action', 'brand.updated')
            ->where('audit.rows.0.actor', 'Linda Park')
            ->where('audit.rows.0.tenant', 'Lone Star Realty')
            ->where('audit.rows.0.after', ['primary' => '#1E3A8A']));
        asComplianceAdmin($this, $ops)->get("/admin/compliance?tenant={$lone->id}")->assertInertia(fn (Assert $page) => $page->has('audit.rows', 2));
        asComplianceAdmin($this, $ops)->get('/admin/compliance?q=lonestar.example')->assertInertia(fn (Assert $page) => $page->has('audit.rows', 1));
        asComplianceAdmin($this, $ops)->get('/admin/compliance?from='.now()->subDays(2)->toDateString())->assertInertia(fn (Assert $page) => $page
            ->where('audit.rows', fn ($rows) => collect($rows)->doesntContain('action', 'state.status_changed'))
            ->where('audit.rows.0.before', 'active'));
        asComplianceAdmin($this, $ops)->get('/admin/compliance?group=nope')->assertSessionHasErrors('group');
    });

    it('exports what the filters show as CSV, safe for spreadsheets, and records the export', function () {
        $ops = complianceOps();
        $evil = User::factory()->create(['name' => '=cmd|calc']);
        app(AuditLogger::class)->record('brand.updated', 'brand', '1', ['before' => ['primary' => null], 'after' => ['primary' => '#1E3A8A']], $evil);

        $csv = asComplianceAdmin($this, $ops)->get('/admin/compliance/audit.csv?group=brand')->assertOk()->streamedContent();

        expect($csv)->toStartWith('time,actor,actor_email,action,tenant,subject,before,after,ip_address,user_agent')
            ->toContain("'=cmd|calc")
            ->toContain('brand.updated')
            ->not->toContain('state.');
        expect(AuditEvent::query()->where('action', 'audit.exported')->sole())
            ->actor_id->toBe($ops->id)
            ->properties->toBe(['filters' => ['group' => 'brand']]);
    });

    it('is for REOS super admins with a recent password confirmation', function () {
        $this->actingAs(User::factory()->withRole(Role::Broker)->withTwoFactor()->create())->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/compliance')->assertForbidden();
        $this->actingAs(complianceOps())->withSession(['auth.password_confirmed_at' => null])->get('/admin/compliance')->assertRedirect();
    });
});

it('has a label for every audit action REOS records', function () {
    $labels = json_decode((string) file_get_contents(base_path('../../packages/i18n/src/locales/en.json')), true)['tenants']['audit']['actions'];
    $missing = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            preg_match_all("/record\\(\\s*'([a-z_]+\\.[a-z_.]+)'/", (string) file_get_contents($file->getPathname()), $found);
            preg_match_all("/\\?\\s*'([a-z_]+\\.[a-z_]+)'\\s*:\\s*'([a-z_]+\\.[a-z_]+)'/", (string) file_get_contents($file->getPathname()), $ternary);
            foreach ([...$found[1], ...$ternary[1], ...$ternary[2]] as $action) {
                if (! isset($labels[$action])) {
                    $missing[] = $action;
                }
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});
