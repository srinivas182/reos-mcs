<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CRM hub (B8.3, ADR 0034): an AI summary and one next step per
 * contact, kept until the contact changes (the fingerprint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('summary');
            $table->string('next_step', 200);
            $table->string('why', 300);
            // call | text | email | note: how the next step is done.
            $table->string('channel', 10);
            // A short text in the agent's voice, sent from their own phone.
            $table->text('draft')->nullable();
            $table->string('language', 5);
            $table->string('fingerprint', 64);
            $table->string('engine', 20);
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_insights');
    }
};
