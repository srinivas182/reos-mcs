<?php

use App\Models\IntegrationCheck;
use App\Models\SyncRun;
use App\Models\WebhookReceipt;
use App\Practice\StudyPlans;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Integration Hub health checks (Sprint 2.4b, ADR 0009).
Schedule::command('reos:integrations:health')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('model:prune', ['--model' => [IntegrationCheck::class]])->daily();

// Accounts (A3.1): delete accounts 30 days after the person asked.
Schedule::command('reos:accounts:purge')->dailyAt('03:30')->onOneServer();

// Connections engine (B3.1, ADR 0013): catch-up syncs, and housekeeping.
// Tenants' own domains waiting for DNS or SSL (A4.2b).
Schedule::command('reos:domains:check')->everyTenMinutes()->withoutOverlapping()->onOneServer();
// Invitations (A4.3a): the one reminder at day 7.
Schedule::command('reos:invites:remind')->hourly()->withoutOverlapping()->onOneServer();
// Privacy (A4.3b): "see my data" files go once their link expires.
Schedule::command('reos:privacy:prune')->dailyAt('04:20')->onOneServer();

Schedule::command('reos:connections:sync')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
// Two-way calendars (B4.1): renew push channels, write changes still waiting, and drop old versions.
Schedule::command('reos:connections:channels')->hourlyAt(7)->withoutOverlapping()->onOneServer();
Schedule::command('reos:calendar:catch-up')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('reos:calendar:prune')->dailyAt('04:35')->onOneServer();
Schedule::command('model:prune', ['--model' => [SyncRun::class, WebhookReceipt::class]])->dailyAt('04:10');
// Knowledge base (A5.3): passages still without an embedding (Titan switched on later, or a failed try).
Schedule::command('reos:knowledge:embed')->hourlyAt(23)->withoutOverlapping()->onOneServer();
// Payments (B5.1): checkouts whose webhook never came, and old unpaid ones.
Schedule::command('reos:billing:reconcile')->everyTenMinutes()->withoutOverlapping()->onOneServer();
// Payouts (B5.2b): schools, instructors and mentors, weekly (Monday morning, US Central).
Schedule::command('reos:payouts:run')->weeklyOn(1, '09:17')->timezone('America/Chicago')->withoutOverlapping()->onOneServer();
// The ledger (B5.3): yesterday's money against each gateway's records.
Schedule::command('reos:ledger:reconcile')->dailyAt('05:20')->withoutOverlapping()->onOneServer();
// Platform health and request metrics (A6.1): screen 58 and the status page.
Schedule::command('reos:health:check')->everyMinute()->withoutOverlapping()->onOneServer();
// Feature flags (A6.2): error rates per flag and the automatic rollback.
Schedule::command('reos:flags:watch')->everyMinute()->withoutOverlapping()->onOneServer();
// The notification centre (B6.2): alerts held in quiet hours, and digests at 8 AM local time.
Schedule::command('reos:notifications:release')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('reos:notifications:digests')->hourlyAt(2)->withoutOverlapping()->onOneServer();
Schedule::command('reos:push:receipts')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('reos:notifications:prune')->dailyAt('04:45')->onOneServer();
// Video sessions (B6.3): rooms close at the booked end; "starting soon" 10 minutes before.
Schedule::command('reos:sessions:tick')->everyMinute()->withoutOverlapping()->onOneServer();
// Recordings are deleted 90 days after the session (B6.3c).
Schedule::command('reos:recordings:prune')->dailyAt('04:15')->onOneServer();
// Course approvals (A7.1): reminders before expiry, and expiries.
Schedule::command('reos:approvals:check')->dailyAt('07:05')->timezone('America/Chicago')->onOneServer();
Schedule::command('reos:lessons:renders')->everyTwoMinutes()->withoutOverlapping()->onOneServer();
// Readiness for the day and plans that follow it (A9.2), just after midnight on candidates' time.
Schedule::command('reos:readiness:nightly')->dailyAt('00:10')->timezone(StudyPlans::TIMEZONE)->withoutOverlapping()->onOneServer();
// Weekly progress reports (A9.2e), Sunday evening on candidates' time.
Schedule::command('reos:reports:weekly')->weeklyOn(0, '17:45')->timezone(StudyPlans::TIMEZONE)->withoutOverlapping()->onOneServer();
// Deal key dates 72 and 24 hours away (B9.1): checked hourly.
Schedule::command('reos:deals:deadlines')->hourlyAt(5)->withoutOverlapping()->onOneServer();

// Booked 1-on-1 sessions (A10.2a): unpaid holds, no-show refunds, completion.
Schedule::command('reos:sessions:bookings')->everyMinute()->withoutOverlapping()->onOneServer();
// The licence exam's reminders (A10.3a): 8:15 in Texas, before the candidate's day starts.
Schedule::command('reos:licence:remind')->dailyAt('08:15')->timezone('America/Chicago')->withoutOverlapping()->onOneServer();
// Regulators' public licence data (B10.3): TREC's and TDI's daily files, before the morning's checks.
Schedule::command('reos:regulators:import')->dailyAt('05:30')->timezone('America/Chicago')->withoutOverlapping()->onOneServer();
// Licence numbers not active yet (A10.3c): checked each morning, after the regulator's nightly file.
Schedule::command('reos:licence:watch')->dailyAt('07:40')->timezone('America/Chicago')->withoutOverlapping()->onOneServer();
// MLS feeds (B10.1): listings replicated every 15 minutes, platform-wide.
Schedule::command('reos:mls:sync')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
// Certificates read but never saved (B10.3b): gone after a day.
Schedule::command('reos:certificates:prune')->dailyAt('04:25')->onOneServer();
