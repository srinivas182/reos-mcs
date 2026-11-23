<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Readiness per candidate per day (A9.2, ADR 0035): the predicted pass
 * chance for each exam portion and each topic's share right, kept nightly
 * and after every test, for trends, the booking line and the weekly report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readiness_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedTinyInteger('national')->nullable();
            $table->unsignedTinyInteger('state')->nullable();
            $table->unsignedTinyInteger('percent')->nullable();
            $table->json('topics');
            $table->json('weakest');
            $table->boolean('ready_to_book')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_snapshots');
    }
};
