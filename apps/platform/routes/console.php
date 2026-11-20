<?php

use App\Models\IntegrationCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Integration Hub health checks (Sprint 2.4b, ADR 0009).
Schedule::command('reos:integrations:health')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('model:prune', ['--model' => [IntegrationCheck::class]])->daily();
