<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses REOS stops emailing (B6.2d, ADR 0023): a hard bounce or a spam
 * complaint reported by Amazon SES. Sending to them again hurts delivery
 * for everyone, so mail to them is dropped until the person resumes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->id();
            // Lower case.
            $table->string('email', 255)->unique();
            // bounce or complaint
            $table->string('reason', 20);
            $table->string('detail', 300)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
