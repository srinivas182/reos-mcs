<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One approvals queue with office admin fallback (A15.3, ADR 0072): when
 * each waiting item was passed to the office admins, so it's passed once;
 * and the brokerage's limit in working hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_fallbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // post, page or question, and its id (uuid or handle).
            $table->string('kind', 10);
            $table->string('ref', 80);
            $table->unsignedInteger('notified');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['organisation_id', 'kind', 'ref']);
        });

        Schema::table('organisations', function (Blueprint $table): void {
            $table->json('approval_settings')->nullable();
        });

        RowLevelSecurity::protect('approval_fallbacks');
    }

    public function down(): void
    {
        RowLevelSecurity::release('approval_fallbacks');
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn('approval_settings');
        });
        Schema::dropIfExists('approval_fallbacks');
    }
};
