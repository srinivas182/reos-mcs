<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tenancy (A4.1, ADR 0015): organisations become tenants, and tenant-owned
 * tables are isolated by PostgreSQL row-level security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            // `<slug>.reos.com`, and the tenant's name in links.
            $table->string('slug', 63)->nullable()->unique()->after('name');
            $table->string('status', 20)->default('active')->index()->after('slug');
            // Where it operates (two-letter state codes).
            $table->json('states')->nullable()->after('status');
            $table->string('plan', 40)->nullable()->after('states');
            // AWS region and cell; tenants move between cells without downtime (screen 59).
            $table->string('region', 20)->default('us-east-2')->after('plan');
            $table->string('cell', 40)->default('cell-1')->after('region');
            // Seats, AI credits, API rate limit and retention (screen 60).
            $table->json('limits')->nullable()->after('cell');
            // Feature switches (screen 60).
            $table->json('features')->nullable()->after('limits');
        });

        // The same rule as new tenants: the name, with the id added only on a clash.
        DB::table('organisations')->orderBy('id')->each(function (object $organisation): void {
            $base = Str::limit(Str::slug((string) $organisation->name) ?: 'tenant', 50, '');
            $taken = DB::table('organisations')->where('slug', $base)->exists();
            DB::table('organisations')->where('id', $organisation->id)->update(['slug' => $taken ? $base.'-'.$organisation->id : $base]);
        });

        // Connections and sign-ins in progress belong to a person or to an
        // organisation; the organisation's are tenant data.
        foreach (['connections', 'oauth_authorizations'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $expression = "case when owner_type = 'organisation' then owner_id end";
                $column = $table->unsignedBigInteger('organisation_id')->nullable();
                // PostgreSQL only has stored generated columns; SQLite can only add virtual ones.
                DB::getDriverName() === 'pgsql' ? $column->storedAs($expression) : $column->virtualAs($expression);
            });

            RowLevelSecurity::protect($name, allowPersonal: true);
        }

        RowLevelSecurity::protect('organisation_hidden_integrations');
    }

    public function down(): void
    {
        RowLevelSecurity::release('organisation_hidden_integrations');

        foreach (['connections', 'oauth_authorizations'] as $name) {
            RowLevelSecurity::release($name);
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('organisation_id');
            });
        }

        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropIndex(['status']);
            $table->dropColumn(['slug', 'status', 'states', 'plan', 'region', 'cell', 'limits', 'features']);
        });
    }
};
