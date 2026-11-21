<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support sign-in with consent (A4.1c, screen 60): REOS support asks, the
 * tenant's owner approves, and support works inside the tenant for 60
 * minutes with a banner, every page logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            // The tenant's admin role support acts in (broker, association_admin…).
            $table->string('role', 40);
            // pending, approved, declined, active, ended, expired, revoked
            $table->string('status', 20)->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'id']);
        });

        RowLevelSecurity::protect('support_access_grants');
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_grants');
    }
};
