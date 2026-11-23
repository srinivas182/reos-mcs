<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly progress reports shared by candidates (A9.2e, screen 55): with
 * their school, or by a link for a study buddy. A share keeps the report as
 * it was that week; schools see a report only once it's shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_shares', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->json('report');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'week_start']);
        });

        // A school's shares are its tenant data; a study-buddy link has no organisation.
        RowLevelSecurity::protect('report_shares', allowPersonal: true);
    }

    public function down(): void
    {
        RowLevelSecurity::release('report_shares');
        Schema::dropIfExists('report_shares');
    }
};
