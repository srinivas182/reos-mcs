<?php

declare(strict_types=1);

use App\Tenancy\Tenancy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outlook Calendar (B4.2): the register entry `microsoft-365` becomes
 * `outlook-calendar`, calendar only (Sprint 4 decision 1). Rows that name
 * it follow.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('microsoft-365', 'outlook-calendar');
    }

    public function down(): void
    {
        $this->rename('outlook-calendar', 'microsoft-365');
    }

    private function rename(string $from, string $to): void
    {
        // Row-level security hides tenant rows from migrations too, unless they run as platform.
        app(Tenancy::class)->asPlatform(function () use ($from, $to): void {
            foreach (['integration_settings', 'integration_credentials', 'integration_checks', 'connections', 'oauth_authorizations', 'webhook_receipts', 'catalogue_settings', 'organisation_hidden_integrations', 'integration_interests', 'tool_requests'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'integration_id')) {
                    DB::table($table)->where('integration_id', $from)->update(['integration_id' => $to]);
                }
            }

            if (Schema::hasTable('calendar_events')) {
                DB::table('calendar_events')->where('source', $from)->update(['source' => $to]);
            }
        });
    }
};
