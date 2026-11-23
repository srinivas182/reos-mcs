<?php

declare(strict_types=1);

use App\Practice\StarterBank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Reference data: REOS's 300-question Texas starter bank, as samples (A9.1, decision 2). */
return new class extends Migration
{
    public function up(): void
    {
        StarterBank::load();
    }

    public function down(): void
    {
        DB::table('quiz_questions')->where('key', 'like', StarterBank::PREFIX.'%')->delete();
    }
};
