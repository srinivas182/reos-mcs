<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deals (B9.1, ADR 0037): one model for every transaction tool and for
 * deals an agent tracks in REOS. Each key date becomes a to-do on that day
 * (and so a calendar entry) with pushes at 72 and 24 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // The agent whose deal it is.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The brokerage, when the deal comes from its tool (brokers see counts and deadlines).
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            // `reos`, or the tool's register id (`lone-wolf`, `dotloop`, `skyslope`).
            $table->string('source', 40)->default('reos');
            $table->string('external_id', 120)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('property', 255);
            $table->string('city', 120)->nullable();
            $table->string('side', 10);
            $table->string('stage', 20);
            $table->unsignedBigInteger('price')->nullable();
            $table->decimal('commission', 12, 2)->nullable();
            $table->json('parties');
            $table->json('documents');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['connection_id', 'external_id']);
            $table->index(['user_id', 'stage']);
        });

        Schema::create('deal_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            // option_period, financing, survey, title_commitment, appraisal, inspection, closing, other.
            $table->string('kind', 30);
            $table->string('label', 120)->nullable();
            $table->date('due_on');
            $table->string('external_id', 120)->nullable();
            $table->foreignId('todo_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('alerted_72_at')->nullable();
            $table->timestamp('alerted_24_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['due_on']);
        });

        RowLevelSecurity::protect('deals', allowPersonal: true);
    }

    public function down(): void
    {
        RowLevelSecurity::release('deals');
        Schema::dropIfExists('deal_dates');
        Schema::dropIfExists('deals');
    }
};
