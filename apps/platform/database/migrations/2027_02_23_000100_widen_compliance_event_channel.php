<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room for the `newsletter` compliance channel (B14.3c, ADR 0068): market
 * update emails are logged like every other channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_events', function (Blueprint $table): void {
            $table->string('channel', 16)->change();
        });
    }

    public function down(): void
    {
        Schema::table('compliance_events', function (Blueprint $table): void {
            $table->string('channel', 8)->change();
        });
    }
};
