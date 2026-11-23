<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart drills and Fix a weak topic (A9.2b, ADR 0035): the hints taken in a
 * drill, and each candidate's short plan for one weak topic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->json('hints')->nullable()->after('flags');
        });

        Schema::create('topic_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 30);
            $table->unsignedTinyInteger('start_percent')->nullable();
            $table->json('steps');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'topic', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_plans');
        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->dropColumn('hints');
        });
    }
};
