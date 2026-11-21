<?php

declare(strict_types=1);

use App\Services\Authorization\RoleCatalogue;
use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants screens (A4.1b, screens 59 and 60): trial end and the reason for a
 * suspension; audit events tagged with their tenant, so each tenant has an
 * audit trail (and, in A4.3, can read it); `tenants.manage` for super admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->timestamp('trial_ends_at')->nullable()->after('status');
            $table->string('status_reason', 500)->nullable()->after('trial_ends_at');
        });

        DB::table('organisations')->whereNull('plan')->update(['plan' => DB::raw("case type when 'association' then 'association' when 'school' then 'school' when 'provider_business' then 'partner' when 'team' then 'inherits' else 'brokerage' end")]);

        Schema::table('audit_events', function (Blueprint $table): void {
            // The tenant an action belongs to; null for platform-wide events.
            $table->foreignId('organisation_id')->nullable()->after('actor_id')->constrained()->nullOnDelete();
            $table->index(['organisation_id', 'id']);
        });

        RowLevelSecurity::protect('audit_events', allowPersonal: true);

        app(RoleCatalogue::class)->sync();
    }

    public function down(): void
    {
        RowLevelSecurity::release('audit_events');

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex(['organisation_id', 'id']);
            $table->dropConstrainedForeignId('organisation_id');
        });

        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn(['trial_ends_at', 'status_reason']);
        });
    }
};
