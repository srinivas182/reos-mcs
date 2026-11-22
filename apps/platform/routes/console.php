<?php

use App\Models\IntegrationCheck;
use App\Models\SyncRun;
use App\Models\WebhookReceipt;
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
