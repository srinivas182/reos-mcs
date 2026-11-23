<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a source knows about a contact that REOS shows but never changes
 * (B7.3, ADR 0029): kvCORE's smart campaign and its status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_links', function (Blueprint $table) {
            $table->json('details')->nullable()->after('echo_hash');
        });
    }

    public function down(): void
    {
        Schema::table('contact_links', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
