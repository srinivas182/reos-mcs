<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The digital card (B14.1, ADR 0064): the phone and email the agent chooses
 * to show on their public page and card (never taken from the account
 * without their say), and the areas they serve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_pages', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('bio');
            $table->string('email', 120)->nullable()->after('phone');
            $table->json('areas')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('agent_pages', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'email', 'areas']);
        });
    }
};
