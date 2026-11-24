<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract review (A12.2, ADR 0048): an agent's review of a contract (a
 * TREC form first): the file (kept with the deal, or with the agent), the
 * step it's on, the key terms with their pages and what changed since the
 * earlier version, the findings ranked High and Check, the deadlines, the
 * questions asked about it, and whether it went to the broker. The pages'
 * words are kept encrypted for "Ask about this contract".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The agent's brokerage, so its broker can see what's sent to them.
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_file_id')->nullable()->constrained()->nullOnDelete();
            // The earlier version this one is compared with.
            $table->foreignId('previous_id')->nullable()->constrained('contract_reviews')->nullOnDelete();
            $table->string('file_name', 200);
            $table->string('path', 300);
            $table->string('mime', 60);
            // "20-18", or null for a document REOS reviews in general.
            $table->string('form', 10)->nullable();
            // reviewing, done or failed.
            $table->string('status', 10);
            // read, terms, checks, compare or done.
            $table->string('step', 8);
            $table->unsignedSmallInteger('pages')->default(0);
            $table->text('page_text')->nullable();
            $table->json('terms')->nullable();
            $table->json('addenda')->nullable();
            $table->json('findings')->nullable();
            $table->json('deadlines')->nullable();
            $table->json('questions')->nullable();
            $table->date('effective_on')->nullable();
            $table->string('language', 5)->default('en');
            $table->string('engine', 10)->default('simulator');
            $table->unsignedSmallInteger('credits')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamp('deadlines_added_at')->nullable();
            $table->foreignId('broker_question_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['deal_id', 'status']);
        });

        RowLevelSecurity::protect('contract_reviews', allowPersonal: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_reviews');
    }
};
