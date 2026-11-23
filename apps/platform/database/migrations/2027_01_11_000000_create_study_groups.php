<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Study groups (A9.3c, screen 52): private groups of up to 20 candidates
 * who opted in from Profile, joined by invite link, with a weekly
 * leaderboard (first names only). A school can make one for a class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('study_groups_opt_in')->default(false);
        });

        Schema::create('study_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 80);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            // A school's class group; candidates' own groups have none.
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('study_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->unique(['study_group_id', 'user_id']);
        });

        Schema::table('challenges', function (Blueprint $table): void {
            $table->foreign('study_group_id')->references('id')->on('study_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropForeign(['study_group_id']);
        });
        Schema::dropIfExists('study_group_members');
        Schema::dropIfExists('study_groups');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('study_groups_opt_in');
        });
    }
};
