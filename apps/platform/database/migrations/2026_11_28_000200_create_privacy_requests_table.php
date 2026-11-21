<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy requests under the Texas Data Privacy and Security Act (A4.3b,
 * screen 71): see, correct or delete one's data, answered within 45 days.
 * A person's request, not a tenant's: REOS answers it, so no tenant column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Who asked, kept while the request is open (cleared when an account is deleted).
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('kind', 12); // access, correct, delete
            $table->text('details')->nullable();
            $table->string('status', 12)->index(); // open, in_progress, hold, done, declined, withdrawn
            $table->timestamp('due_at')->index();
            $table->boolean('extended')->default(false);
            $table->text('response')->nullable();
            $table->string('export_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
