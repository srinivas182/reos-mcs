<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way CRM sync (B7.2, ADR 0028): a note written in REOS remembers its
 * id in each CRM it was sent to, so it isn't read back as a new note; a
 * CRM's notes are found by their own id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            // {"follow-up-boss": "5012"}: where REOS sent this note, and its id there.
            $table->json('pushed')->nullable()->after('external_id');
            $table->index(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->dropIndex(['source', 'external_id']);
            $table->dropColumn('pushed');
        });
    }
};
