<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school's limit on its instructors' 1-on-1 session prices (A10.2d,
 * sprint decision 3; school admin screen 52). Null: no limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->unsignedInteger('session_max_30_cents')->nullable();
            $table->unsignedInteger('session_max_60_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn(['session_max_30_cents', 'session_max_60_cents']);
        });
    }
};
