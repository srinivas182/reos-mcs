<?php

declare(strict_types=1);

use App\Services\Authorization\RoleCatalogue;
use Illuminate\Database\Migrations\Migration;

/** Adds `states.manage` (A3.3) to the stored roles; super admins get it. */
return new class extends Migration
{
    public function up(): void
    {
        app(RoleCatalogue::class)->sync();
    }

    public function down(): void
    {
        // Permissions defined in code stay; nothing to undo.
    }
};
