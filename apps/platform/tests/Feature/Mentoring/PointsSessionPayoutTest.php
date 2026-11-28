<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Billing\Ledger\Points;
use App\Billing\Payouts\PayoutLineStatus;
use App\Billing\Payouts\PayoutRun;
use App\Features\Flags;
use App\Mentoring\MentorProgram;
use App\Models\AgentProfile;
use App\Models\ConnectedAccount;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PayoutLine;
use App\Models\SessionBooking;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * Points-funded mentor sessions, paid once they happen (A17.1, ADR 0082).
 */

beforeEach(function () {
    Notification::fake();
    $flags = app(Flags::class);
    $flag = $flags->flag('video_rooms');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));

    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->dana = User::factory()->withRole(Role::Mentor, $this->lone)->create(['name' => 'Dana Brooks']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Priya Shah']);
    AgentProfile::query()->create(['user_id' => $this->priya->id, 'goal' => 'launch']);
    $this->actingAs($this->dana)->putJson('/api/v1/bookings/offer', [
        'bio' => 'Twelve years in Katy.', 'languages' => ['en'], 'topics' => [], 'hours' => ['mon' => [['13:00', '17:00']]],
        'timezone' => 'America/Chicago', 'price_30_cents' => 0, 'price_60_cents' => 0, 'active' => true,
    ])->assertOk();
    app(MentorProgram::class)->save($this->lone, $this->dana, ['topics' => ['listings'], 'price_30_cents' => 4000, 'price_60_cents' => 7500, 'points' => true, 'max_mentees' => 4, 'who' => 'everyone'], $this->linda);
    // Dana's Stripe account can take payouts.
    ConnectedAccount::query()->create(['owner_type' => 'user', 'owner_id' => $this->dana->id, 'environment' => 'simulator', 'kind' => 'express', 'stripe_account_id' => 'sim_acct_dana', 'details_submitted' => true, 'payouts_enabled' => true]);
    app(Points::class)->award($this->priya, 10000, 'test:grant');
});

function brokeragePoints(Organisation $org): int
{
    $ledger = app(Ledger::class);
    $account = $ledger->find("organisation:{$org->id}:points");

    return $account === null ? 0 : $ledger->balance($account);
}

describe('points sessions (A17.1)', function () {
    it('pays the mentor for a points session once it happens, funded by the brokerage (the done-when)', function () {
        $booked = $this->actingAs($this->priya)->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-23T20:00:00Z', 'minutes' => 60, 'topic' => 'Winning a listing', 'pay_with' => 'points'])
            ->assertCreated()->json('data');
        $booking = SessionBooking::query()->where('uuid', $booked['uuid'])->sole();
        $line = PayoutLine::query()->where('order_id', $booking->order_id)->where('payee_type', 'user')->where('payee_id', $this->dana->id)->sole();

        // Funded by Lone Star's points account; Dana's share waits for the session.
        expect(brokeragePoints($this->lone))->toBe(-7500)
            ->and($line->amount_cents)->toBe(6750)
            ->and($line->status)->toBe(PayoutLineStatus::Held);
        expect(app(PayoutRun::class)->run()['payouts'])->toBe(0);
        $this->actingAs($this->dana)->getJson('/api/v1/me/earnings')->assertOk()->assertJsonPath('held', 6750);

        // Dana teaches; the session ends and is complete: her share goes out in the next payout.
        $session = VideoSession::query()->findOrFail($booking->video_session_id);
        $session->participants()->where('role', VideoSessionParticipant::HOST)->update(['first_joined_at' => now()]);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 15:05', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        $this->artisan('reos:sessions:bookings')->assertSuccessful();
        expect($booking->refresh()->status)->toBe('completed')->and($line->refresh()->status)->toBe(PayoutLineStatus::Pending);

        $result = app(PayoutRun::class)->run();
        expect($result['payouts'])->toBe(1)
            ->and(Payout::query()->sole()->amount_cents)->toBe(6750)
            ->and($line->refresh()->status)->toBe(PayoutLineStatus::Paid);
    });

    it('returns the points and the brokerage\'s funding when the mentor doesn\'t show', function () {
        $booked = $this->actingAs($this->priya)->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-23T20:00:00Z', 'minutes' => 30, 'topic' => 'Pricing', 'pay_with' => 'points'])->assertCreated()->json('data');
        expect(app(Points::class)->balance($this->priya))->toBe(6000)->and(brokeragePoints($this->lone))->toBe(-4000);

        $this->travelTo(CarbonImmutable::parse('2026-11-23 14:15', 'America/Chicago'));
        $this->artisan('reos:sessions:bookings')->assertSuccessful();

        expect(SessionBooking::query()->where('uuid', $booked['uuid'])->sole()->status)->toBe('no_show')
            ->and(app(Points::class)->balance($this->priya))->toBe(10000)
            ->and(brokeragePoints($this->lone))->toBe(0)
            ->and(PayoutLine::query()->where('payee_type', 'user')->where('payee_id', $this->dana->id)->sole()->status)->toBe(PayoutLineStatus::Cancelled);
    });
});
