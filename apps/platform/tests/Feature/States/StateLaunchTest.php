<?php

declare(strict_types=1);

use App\Enums\StateStatus;
use App\Models\AuditEvent;
use App\Models\State;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\StateWentLive;
use App\Services\Onboarding\States;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->ops = User::factory()->superAdmin()->create();
});

describe('launching a state (web journey screen 61)', function () {
    it('adds a state: in preparation, with its regulator and hours, audited', function () {
        $this->actingAs($this->ops)->put('/admin/states/LA/details', [
            'regulator' => 'LREC', 'exam_vendor' => 'PSI', 'qualifying_hours' => 90, 'ce_hours' => 12,
        ])->assertRedirect();

        expect(State::query()->findOrFail('LA'))
            ->status->toBe(StateStatus::InPreparation)->regulator->toBe('LREC')->exam_vendor->toBe('PSI')->qualifying_hours->toBe(90)->ce_hours->toBe(12)
            ->and(AuditEvent::query()->where('action', 'state.prepared')->sole()->properties['after'])
            ->toBe(['regulator' => 'LREC', 'exam_vendor' => 'PSI', 'qualifying_hours' => 90, 'ce_hours' => 12]);
    });

    it('shows the checklist and courses on the screen', function () {
        $this->actingAs($this->ops)->put('/admin/states/LA/checklist', ['item' => 'rules', 'done' => true]);

        $this->get('/admin/states')->assertInertia(fn (Assert $page) => $page
            ->where('checklistItems', States::CHECKLIST)
            ->where('states', fn ($states) => collect($states)->firstWhere('code', 'LA')['checklist'] === [
                'rules' => true, 'approvals' => false, 'advertising' => false, 'plans' => false, 'integrations' => false, 'legal' => false,
            ] && collect($states)->firstWhere('code', 'LA')['courses_approved'] === 0));
    });

    it("won't go live until the checklist is complete, then tells the waitlist once", function () {
        $waiting = User::factory()->create(['email' => 'ana@example.com', 'locale' => 'es']);
        WaitlistEntry::query()->create(['state_code' => 'LA', 'user_id' => $waiting->id]);
        WaitlistEntry::query()->create(['state_code' => 'LA', 'device_id' => '3f1b9f2e-6d1c-4d55-9a7e-1c2b3d4e5f61', 'email' => 'sam@example.com']);
        WaitlistEntry::query()->create(['state_code' => 'LA', 'device_id' => '3f1b9f2e-6d1c-4d55-9a7e-1c2b3d4e5f62']);

        $this->actingAs($this->ops)->patch('/admin/states/LA', ['status' => 'live'])->assertSessionHasErrors(['status' => "Louisiana can't go live yet: finish its launch checklist first."]);
        expect(State::query()->findOrFail('LA')->status)->toBe(StateStatus::Waitlist);

        foreach (States::CHECKLIST as $item) {
            $this->put('/admin/states/LA/checklist', ['item' => $item, 'done' => true])->assertRedirect();
        }
        $this->put('/admin/states/LA/checklist', ['item' => 'nonsense', 'done' => true])->assertSessionHasErrors('item');

        $this->patch('/admin/states/LA', ['status' => 'live'])->assertSessionHasNoErrors();

        $state = State::query()->findOrFail('LA');
        expect($state->status)->toBe(StateStatus::Live)->and($state->went_live_at)->not->toBeNull();
        Notification::assertSentTo($waiting, StateWentLive::class);
        Notification::assertSentTo(new AnonymousNotifiable, StateWentLive::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'sam@example.com');
        Notification::assertCount(2);
        expect(WaitlistEntry::query()->whereNull('notified_at')->count())->toBe(1);

        // Back to preparation and live again: nobody is told twice.
        $this->patch('/admin/states/LA', ['status' => 'in_preparation']);
        $this->patch('/admin/states/LA', ['status' => 'live']);
        Notification::assertCount(2);
    });

    it('writes the go-live email in the person\'s language', function () {
        $mail = (new StateWentLive(State::query()->findOrFail('LA')))->toMail(User::factory()->make());

        expect($mail->subject)->toBe('REOS is now live in Louisiana');
    });

    it('validates the details', function () {
        $this->actingAs($this->ops)->put('/admin/states/LA/details', ['regulator' => '', 'qualifying_hours' => -1])
            ->assertSessionHasErrors(['regulator', 'qualifying_hours']);
    });

    it('is for super admins only', function () {
        $this->actingAs(User::factory()->create())->put('/admin/states/LA/checklist', ['item' => 'rules', 'done' => true])->assertForbidden();
    });
});
