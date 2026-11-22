<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content review (A5.3b, screen 63): legal flags a reviewer has checked and
 * cleared (by passage and rule), so a cleared line stops blocking the
 * course, and who reviewed it last.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            // [{key, by, at}]: a flagged passage and rule a reviewer checked.
            $table->json('cleared_flags')->nullable()->after('approvals');
            $table->foreignId('reviewed_by')->nullable()->after('status_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['cleared_flags', 'reviewed_at']);
        });
    }
};
