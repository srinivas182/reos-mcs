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
Schedule::command('model:prune', ['--model' => [SyncRun::class, WebhookReceipt::class]])->dailyAt('04:10');
