<?php

declare(strict_types=1);

use App\Practice\DiagnosticBank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Reference data: REOS's 50 Texas diagnostic questions (A8.3, decision 4). */
return new class extends Migration
{
    public function up(): void
    {
        DiagnosticBank::load();
    }

    public function down(): void
    {
        DB::table('quiz_questions')->where('key', 'like', 'diagnostic-tx:%')->delete();
    }
};
