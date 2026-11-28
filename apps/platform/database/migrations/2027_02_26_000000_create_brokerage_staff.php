<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * People and permissions, and brokerage rules (A15.2, ADR 0071).
 *
 * - `brokerage_staff`: each person holding the broker role in a brokerage
 *   or its teams is its designated broker, an office admin or a team
 *   leader, with the duties the designated broker turned on.
 * - `policy_passages.conflict`: a brokerage rule or FAQ answer written in
 *   REOS that contradicts a TREC rule, found as it's saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brokerage_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 20);
            $table->json('duties');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'user_id']);
        });

        Schema::table('policy_passages', function (Blueprint $table): void {
            $table->json('conflict')->nullable();
        });

        // Each brokerage's first broker is its designated broker (they set it up).
        $role = DB::table('roles')->where('name', 'broker')->value('id');
        $brokerages = DB::table('organisations')->where('type', 'brokerage')->pluck('id');

        foreach ($role === null ? [] : $brokerages as $brokerage) {
            $first = DB::table('model_has_roles')->where('role_id', $role)->where('organisation_id', $brokerage)->min('model_id');

            if ($first !== null) {
                DB::table('brokerage_staff')->insert(['organisation_id' => $brokerage, 'user_id' => $first, 'title' => 'designated_broker', 'duties' => '[]', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        RowLevelSecurity::protect('brokerage_staff');
    }

    public function down(): void
    {
        RowLevelSecurity::release('brokerage_staff');
        Schema::table('policy_passages', function (Blueprint $table): void {
            $table->dropColumn('conflict');
        });
        Schema::dropIfExists('brokerage_staff');
    }
};
